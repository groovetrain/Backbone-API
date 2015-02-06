<?php

class BackboneAPIModel {
	// Static
	public static $table_name;
	public static $fields;
	public static $attr_accessible;
	public static $db_version;
	public static $db_schema;

	static function migrater() {
		global $wpdb;
		$version_option_name = static::$table_name."_migration_version";
		$prefixed_table_name = $wpdb->prefix.static::$table_name;
		$installed_version = get_option($version_option_name);
		if (get_option($version_option_name) != static::$db_version) {
			static::migrate($installed_version, $wpdb);
			update_option($version_option_name, static::$db_version);
		}
	}

	// Instance
	private $wpdb;
	private $_prefixed_table_name;

	function BackboneAPIModel() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->_prefixed_table_name = $this->wpdb->prefix.''.static::$table_name;
	}

	function find_all($attrs) {
		$sql = $this->_generate_select($attrs);
		return $this->_decoerce_needed_values($this->wpdb->get_results($sql), true);
	}

	function find_one($attrs) {
		$attrs['limit'] = 1;
		$sql = $this->_generate_select($attrs);
		return $this->_decoerce_needed_values($this->wpdb->get_row($sql));
	}

	function create($attrs) {
		$params = isset($attrs['params']) ? $this->_attr_accessible_only_params($attrs['params']) : array();
		$params = $this->create_scope($params);
		$params = $this->_coerce_needed_values($params);
		if (empty($params)) {
			return false;
		}

		$sprintf_array = $this->_generate_sprintf_array(array_keys($params));
		$resp = $this->wpdb->insert($this->_prefixed_table_name, $params, $sprintf_array);
		if ($resp == false) {
			return false;
		} else {
			return $this->find_one(array('conditions' => array(array('field' => 'id', 'value' => $this->wpdb->insert_id))));
		}
	}

	function update($attrs) {
		$attrs['conditions'] = isset($attrs['conditions']) ? $attrs['conditions'] : array();
		$params = isset($attrs['params']) ? $this->_attr_accessible_only_params($attrs['params']) : array();
		if( method_exists($this, 'update_scope') )
			$params = $this->update_scope($params);
		$params = $this->_coerce_needed_values($params);
		if (empty($params)) {
			return false;
		}

		$set = $this->_generate_set_sql($params);
		$conditions = $this->_generate_sql_conditions($attrs['conditions']);
		$sql = "UPDATE ".$this->_prefixed_table_name." SET ".$set." WHERE ".$conditions;
		$resp = $this->wpdb->query($sql);
		if ($resp !== false) {
			return $this->find_one(array('conditions' => $attrs['conditions']));
		} else {
			return false;
		}
	}

	function delete($attrs) {
		$attrs['conditions'] = isset($attrs['conditions']) ? $attrs['conditions'] : array();
		$conditions = $this->_generate_sql_conditions($attrs['conditions']);
		$record = $this->find_one(array('conditions' => $attrs['conditions']));
		if ($record) {
			$sql = "DELETE FROM ".$this->_prefixed_table_name." WHERE ".$conditions;
			$resp = $this->wpdb->query($sql);
			return $record;
		} else {
			return false;
		}


	}

	function custom_create_params($params) {
		return $params;
	}

	// Generate a select statement
	function _generate_select($attrs) {
		$sql = '';
		$attrs['conditions'] = isset($attrs['conditions']) ? $attrs['conditions'] : array();
		$conditions = $this->_generate_sql_conditions($attrs['conditions']);
		$sql = "SELECT * FROM ".$this->_prefixed_table_name;
		if ($conditions)
			$sql .= " WHERE ".$conditions;
		if (isset($attrs['limit']))
			$sql .= $this->wpdb->prepare(" LIMIT %d", $attrs['limit']);
		if (isset($attrs['offset']))
			$sql .= $this->wpdb->prepare(" OFFSET %d", $attrs['offset']);
		return $sql;
	}

	function _generate_set_sql($params) {
		$sql_parts = array();
		foreach ($params as $field_name => $value) {
			$sql_parts[] = $this->wpdb->prepare($field_name." = ".$this->_sprintf_format_for_field($field_name), $value);
		}
		return implode(', ', $sql_parts);
	}

	// Generates the where clause of an sql select statement
	function _generate_sql_conditions($conditions) {
		$sql_parts = array();
		$condition_values = array();
		foreach ($conditions as $condition) {
			if (isset($condition['field']) && isset($condition['value'])) {
				$operator = isset($condition['operator']) ? $condition['operator'] : '=';
				$sql_parts[] = $condition['field'].' '.$operator.' '.$this->_sprintf_format_for_field($condition['field']);
				$condition_values[] = $this->_is_dateish_field($condition['field']) ? $this->_format_date_type_for_sql($condition['field'], $condition['value']) : $condition['value'];
			} else {
				$or_sql_parts = array();
				foreach ($condition as $or_condition) {
					if (isset($or_condition['field']) && isset($or_condition['value'])) {
						$operator = isset($or_condition['operator']) ? $or_condition['operator'] : '=';
						$or_sql_parts[] = $or_condition['field'].' '.$operator.' '.$this->_sprintf_format_for_field($or_condition['field']);
						$condition_values[] = $this->_is_dateish_field($or_condition['field']) ? $this->_format_date_type_for_sql($or_condition['field'], $or_condition['value']) : $or_condition['value'];
					}
				}
				if (sizeof($or_sql_parts) > 0) {
					$sql_parts[] = "(".implode(' OR ', $or_sql_parts).")";
				}
			}
		}
		return $this->wpdb->prepare(implode(' AND ', $sql_parts), $condition_values);
	}

	// Returns %s for stringish fields, %d for integerish fields, and %f for floatish fields
	function _sprintf_format_for_field($field_name) {
		$float_types = array('float','double','decimal');
		$string_types = array('char','varchar','tinytext','text','blob','mediumtext','mediumblob','longtext','longblob','enum','set');
		$date_types = array('date','datetime','timestamp','time','year');
		$int_types = array('tinyint','smallint','mediumint','int','bigint');
		$field_type = $this->_get_field_type($field_name);
		if (in_array($field_type, $float_types)) {
			return '%f';
		} else if (in_array($field_type, $string_types) || in_array($field_type, $date_types)) {
			return '%s';
		} else if (in_array($field_type, $int_types)) {
			return '%d';
		} else {
			return '%s';
		}
	}

	// When passed an array of field names it returns an array of sprintf format strings
	function _generate_sprintf_array($field_names) {
		$sprintf_formats = array();
		foreach ($field_names as $field_name) {
			$sprintf_formats[] = $this->_sprintf_format_for_field($field_name);
		}
		return $sprintf_formats;
	}

	// Takes a string representation of a date and transforms it into the relevant date format expected by mysql
	function _format_date_type_for_sql($field_name, $value) {
		$strftime_formats = array(
			'date' => '%Y-%m-%d',
			'datetime' => '%Y-%m-%d %H:%M:%S',
			'timestamp' => '%Y-%m-%d %H:%M:%S',
			'time' => '%H:%M:%S',
			'year' => '%Y'
		);
		return strftime($strftime_formats[$this->_get_field_type($field_name)], strtotime($value));
	}

	// Takes a field name, returns the mysql type for the field
	function _get_field_type($field_name) {
		return static::$fields[$field_name]['type'];
	}

	function _coerce_needed_values($params) {
		foreach ($params as $field_name => $val) {
			if ($this->_is_dateish_field($field_name)) {
				$params[$field_name] = $this->_format_date_type_for_sql($field_name, $val);
			}

			if (isset(static::$fields[$field_name]['serialize'])) {
				$params[$field_name] = call_user_func(array($this,static::$fields[$field_name]['serialize']), $val);
			}
		}
		return $params;
	}

	function _decoerce_needed_values($params, $is_array_of_records=false) {
		$fields_to_decoerce = array();
		foreach (static::$fields as $field => $conf) {
			if (isset($conf['deserialize'])) {
				$fields_to_decoerce[] = $field;
			}
		}
		if ($is_array_of_records) {
			foreach ($params as $record) {
				foreach ($fields_to_decoerce as $field_name) {
					$record->$field_name = call_user_func(array($this,static::$fields[$field_name]['deserialize']), $record->$field_name);
			}
			}
		} else {
			foreach ($fields_to_decoerce as $field_name) {
				$params->$field_name = call_user_func(array($this,static::$fields[$field_name]['deserialize']), $params->$field_name);
			}
		}
		return $params;
	}

	// Takes a field's name and returns whether or not is a date like type in mysql
	function _is_dateish_field($field_name) {
		return in_array($this->_get_field_type($field_name), array('date','datetime','timestamp','time','year'));
	}

	function _is_integerish_field($field_name) {
		return in_array($this->_get_field_type($field_name), array('tinyint','smallint','mediumint','int','bigint'));
	}

	function _is_floatish_field($field_name) {
		return in_array($this->_get_field_type($field_name), array('float','double','decimal'));
	}

	function _is_stringish_field($field_name) {
		return in_array($this->_get_field_type($field_name), array('char','varchar','tinytext','text','blob','mediumtext','mediumblob','longtext','longblob','enum','set'));
	}

	// Takes an array of params (generally from the $_POST var) and returns only params have been
	// whitelisted using attr_accessible
	function _attr_accessible_only_params($params) {
		$white_listed = array();
		foreach ($params as $key => $val) {
			if (in_array($key, static::$attr_accessible)) {
				$white_listed[$key] = $val;
			}
		}
		return $white_listed;
	}
}