<?php
/*
$model_class is the class of the BackboneAPIModel that this router exposes
$resource_slug is the slug for the resource. domain.com/{$resource_slug}/1

By default the router exposes the following actions:
	index = GET
	create = POST
	view = GET
	update = PUT
	delete = DELETE
You can pick and choose which of these actions are created by setting one of the two following variables:
	$include = array(); - put the names of the actions you would to have created in the array
	$exclude = array(); - put the names of the actions you would not like to have created in the array
Only use one of these variables at a time

Each of these actions has an authorizer function that will run before its actual action is called to see if
it should actuall be called. By default these actions just return true, you can override them in your
custom class to provide a custom authorization scheme. These functions are named as follows:
	index_authorizer - runs before index action
	create_authorizer - runs before the create action
	view_authorizer -  runs before view action
	update_authorizer -  runs before update action
	delete_authorizer -  runs before delete action

There is also the ability to add a global scope method or per action scope methods that will be added to each of the
database statements made.
The way this works is to override the 'scope' method in your class to return an array of fields and values that
you want them to match. Each item in the array will be AND'ed to any other items in the array. If an item in the array
is a an array of conditions it will be wrapped in () and AND'ed to the rest of the conditions, however the conditions in
this subarray will be OR'ed together.

Each condition array must contain a 'field' and 'value' key and can contain an optional 'operator' key which will hold
what operator is used on the field and value. The following are a few examples of how to use this function

function scope() {
	$scope = array();

	// To match against the current user. 'get_current_user_id()' is a wordpress function to get the logged in user id.
	$scope[] = array('field' => 'user_id', 'value' => get_current_user_id());
	// Produces the following condition part for the where clause:
	//	user_id = {value of get_current_user_id()}

	// To match around dates or date ranges. For instance all people born in the year 1986
	$scope[] = array('field' => 'birthdate', 'operator' => '>=' 'value' => strtotime('1/1/1986'));
	$scope[] = array('field' => 'birthdate', 'operator' => '<=' 'value' => strtotime('12/31/1986'));
	// Produces the following condition part for the where clause:
	//	birthdate >= 1986-01-01 AND birthdate <= 1986-12-31

	// To match against multiple criteria that are OR'ed together:
	$scope[] = array(
		array('field' => 'name', 'value' => 'John'),
		array('field' => 'name', 'value' => 'Josh')
	);
	// Produces the following condition part for the where clause:
	//	(name = 'John' OR name = 'Josh')

	return $scope;
}

Scopes are added on top of the default 'id' scope that will be used on the view, update, and delete actions. Scopes
will probably me most useful for limiting actions to records that are owned by the current user.

The scope names are as follows:
	scope - scopes all of the actions
	index_scope - only scopes the index action
	view_scope - only scopes the view action
	update_scope - only scopes the update action
	delete_scope - only scopes the delete action

The global scope will be merged with the action specific scopes if they exist.

There is no scoping on the create action. Scopes are applied using a WHERE statement to the sql that is issued
and because there is no WHERE clause in a create action there is no scoping with create.
*/

class BackboneAPIRestfulController extends BackboneAPIController {
	public static $model_class;
	public static $resource_slug;
	public static $include;
	public static $exclude;

	var $model;

	function BackboneAPIRestfulController() {
		if (static::$model_class) {
			$this->model = new static::$model_class();
		}

		$routes = array();
		$action_method_mapping = array(
			'index' => 'GET',
			'create' => 'POST',
			'view' => 'GET',
			'update' => 'PUT',
			'delete' => 'DELETE'
		);

		$actions_to_create = is_array(static::$include) ? static::$include : array('index','create','view','update','delete');
		if (is_array(static::$exclude)) {
			foreach (static::$exclude as $exclude) {
				unset($actions_to_create[$exclude]);
			}
		}

		if (in_array('index', $actions_to_create) || in_array('create', $actions_to_create)) {
			$path = '^'.static::$resource_slug.'$';
			$callbacks = array();
			$access_callbacks = array();
			foreach($actions_to_create as $action) {
				if ($action == 'index' || $action == 'create') {
					$callbacks[$action_method_mapping[$action]] = $action;
					$access_callbacks[$action_method_mapping[$action]] = $action.'_authorizer';
				}
			}
			if (sizeof($callbacks) > 0) {
				$routes[static::$resource_slug.'_collection_actions'] = array(
					'path' => $path,
					'params' => $params,
					'callback' => $callbacks,
					'authorizer' => $access_callbacks
				);
			}
		}

		if (sizeof($actions_to_create) > 0) {
			$path = '^'.static::$resource_slug.'/(.*?)$';
			$params = array('id');
			$callbacks = array();
			$access_callbacks = array();
			foreach ($actions_to_create as $action) {
				if ($action != 'index' && $action != 'create') {
					$callbacks[$action_method_mapping[$action]] = $action;
					$access_callbacks[$action_method_mapping[$action]] = $action.'_authorizer';
				}
			}
			if (sizeof($callbacks) > 0) {
				$routes[static::$resource_slug.'_instance_actions'] = array(
					'path' => $path,
					'params' => $params,
					'callback' => $callbacks,
					'authorizer' => $access_callbacks
				);
			}
		}

		// foreach ($actions_to_create as $action) {
		// 	$path = ($action == 'index') ? '^'.static::$resource_slug.'$' : '^'.static::$resource_slug.'/(.*?)$';
		// 	$params = ($action == 'index') ? array() : array('id');

		// 	$routes[static::$resource_slug.'_'.$action] = array(
		// 		'template' => false,
		// 		'path' => $path,
		// 		'params' => $params,
		// 		'callback' => array(
		// 			$action_method_mapping[$action] => $action
		// 		),
		// 		'access_callback' => array(
		// 			$action_method_mapping[$action] => $action.'_authorizer'
		// 		)
		// 	);
		// }
		static::$routes = $routes;
		parent::__construct();
	}

	// Default scopes - Override in custom classes
	function scope() {
		return array();
	}

	function scope_index() {
		return array();
	}

	function scope_view() {
		return array();
	}

	function scope_update() {
		return array();
	}

	function scope_delete() {
		return array();
	}

	// Default authorizers - Override in custom classes
	function index_authorizer() {
		return true;
	}

	function create_authorizer() {
		return true;
	}

	function view_authorizer() {
		return true;
	}

	function update_authorizer() {
		return true;
	}

	function delete_authorizer() {
		return true;
	}

	// Default actions - These can be overridden in your custom classes if needed
	function index() {
		$conditions = array_merge($this->scope(), $this->scope_index());
		$resp = $this->model->find_all(array('conditions' => $conditions));
		$this->send_json($resp);
	}

	function create() {
		$resp = $this->model->create(array('params' => $this->post));
		$this->send_json($resp);
	}

	function view() {
		$conditions = array_merge($this->scope(), $this->scope_view());
		$conditions[] = array('field' => 'id', 'value' => $this->get['id']);
		$resp = $this->model->find_one(array('conditions' => $conditions));
		if (sizeof($resp) > 0) {
			$this->send_json($resp);
		} else {
			$this->respond_404();
		}
	}

	function update() {
		$conditions = array_merge($this->scope(), $this->scope_view());
		$conditions[] = array('field' => 'id', 'value' => $this->get['id']);
		$params = $this->post;
		$resp = $this->model->update(array('params' => $params, 'conditions' => $conditions));
		if ($resp && sizeof($resp) > 0) {
			$this->send_json($resp);
		} else {
			$this->respond_404();
		}
	}

	function delete() {
		$conditions = array_merge($this->scope(), $this->scope_delete());
		$conditions[] = array('field' => 'id', 'value' => $this->get['id']);
		$resp = $this->model->delete(array('conditions' => $conditions));
		if ($resp && sizeof($resp) > 0) {
			$this->send_json($resp);
		} else {
			$this->respond_404();
		}
	}
}