<?php
class BackboneAPIController {
	public static $routes;

	var $wpdb;
	var $_params = false;
	function BackboneAPIController() {
		global $wpdb;
		$this->wpdb = $wpdb;
		add_action('wp_router_generate_routes', array($this, 'add_routes'), 20);
	}

	function add_routes($router) {
		foreach (static::$routes as $name => $attrs) {
			$router_args = array(
				'path' => $attrs['path'],
				'template' => false,
				'query_vars' => array(),
				'page_arguments' => array()
			);
			if (isset($attrs['callback'])) {
				$router_args['page_callback'] = array($this, 'dispatch');
			}
			if (isset($attrs['params'])) {
				$attrs['params'] = is_array($attrs['params']) ? $attrs['params'] : array($attrs['params']);
				foreach ($attrs['params'] as $index => $param_name) {
					$router_args['query_vars'][$param_name] = $index + 1;
				}
			}
			$router->add_route($name,$router_args);
		}
	}

	function dispatch() {
		global $wp;
		$this->_current_route_name = $wp->query_vars['WP_Route'];
		$this->_current_route = static::$routes[$this->_current_route_name];
		// Set up get and post vars
		$this->get = $_GET;
		$this->post = $_POST;

		// Map url params into the get array
		foreach ($wp->query_vars as $key => $val) {
			if ($key != 'WP_Route') {
				$this->get[$key] = $val;
			}
		}

		// If the request method is put, php won't fill out the post array with the vars so we handle it ourselves
		if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
			$php_input = file_get_contents("php://input");
			if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
				$this->post = json_decode($php_input, true);
			} else {
				parse_str($php_input, $this->post);
			}
		}
		$method = (isset($_REQUEST['_action'])) ? $_REQUEST['action'] : $_SERVER['REQUEST_METHOD'];
		$callback = false;
		$before_callback = false;
		if ( is_callable(array($this, $this->_current_route['callback']))) {
			$callback = array($this, $this->_current_route['callback']);
			if (is_callable(array($this, 'before_'.$this->_current_route['callback'])))
				$before_callback = array($this, 'before_'.$this->_current_route['callback']);
		} else {
			if (is_array($this->_current_route['callback'])) {
				if ($method && isset($this->_current_route['callback'][$method]) && is_callable(array($this, $this->_current_route['callback'][$method]))) {
					$callback = array($this, $this->_current_route['callback'][$method]);
					if (is_callable(array($this, 'before_'.$this->_current_route['callback'][$method])))
						$before_callback = array($this, 'before_'.$this->_current_route['callback'][$method]);
				} else {
					$this->respond_404();
				}
			}
		}

		if ($before_callback != false) {
			call_user_func($before_callback);
		}
		call_user_func($callback);
	}

	function respond_404($msg = "Resource could not be found") {
		return $this->send_json(array('error' => $msg), 'HTTP/1.0 404 Not Found');
	}

	function respond_403($msg = "You don't have permission to access this resource") {
		return $this->send_json(array('error' => $msg), 'HTTP/1.0 403 Forbidden');
	}

	function send_json($payload, $extra_headers = false) {
		header('Content-Type: application/json');
		if ($extra_headers) {
			$extra_headers = is_array($extra_headers) ? $extra_headers : array($extra_headers);
			foreach ($extra_headers as $extra_header) {
				header($extra_header);
			}
		}
		echo json_encode($payload);
		exit();
	}
}