=== Backbone API ===
Contributors: craigkuhns
Tags: api, backbone, rest
Requires at least: 3.9
Tested up to: 3.9
Stable tag: trunk

Provides a simple api for create restful controllers specifically for Backbone.

== Description ==

WordPress is a powerful CMS and provides a lot of flexibility to manage custom
content types that can fit inside of the custom post type paradigm but for
times when you need to expose a restful api for custom database tables it
can fall short.

Backbone API tries to make this much easier. It provides a simple model class, a
flexible controller class, and a restful controller that takes most of the work
out of creating restful api's.

== Installation ==

1. Download and unzip the plugin
2. Upload the `backbon-api` folder to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress

== Usage ==

= Creating Models =

Models are created by extending from the BackboneAPIModel class. A model must have
following elements:

* `public static $table_name = ''` - this holds the non-prefixed name of your model
	database table.
* `public static $fields = array()` - this is an array of the database columns. The
	key is the name of the column in the database. The value is an array that for now
	contains one element, type, which will be a mysql type.
* `static $attr_accessible = array()`  - this is required for security's sake. This
	is an array of database columns that can be mass-assigned through the create and
	update.

Models have an optional migration component that you can chose to implement. This allows
you to manage the database table for the model. Here is how it works. You register a couple
of hooks for the models migrate function to be run when the plugin is first activated
or when the installed version of the table does not match up with the newest version in the
plugin. When the plugin is loaded it checks to see if the installed version matches the
current version defined in the plugin. If it does not it will run the defined migrate function.
Inside your migrate funciton you should check and see if there is any version of the database
installed and if not then run a create statement. After that there should be a series of
functions checking the installed version against the various version numbers your table
has gone through and running then needed sql statements to bring the table up to date. The
BackboneAPIModel will keep the installed version of the table up to date in the `wp_options`
behind the scenes.

Note: I tried making this work with dbDelta. I kept running into a lot of quirks with
how dbDelta works and how particular it is with how the sql is formatted. It also
doesn't do things like drop columns automatically or rename them. I found the most flexible
way to handle this is to not use it at all and to use raw sql and run through a series
of sql statements to end up with the final table you want.

Here is how to actually use it

* Add the following wordpress hooks:
	`register_activation_hook(__FILE__, array(Model, 'migrater'));`
	`add_action('plugins_loaded', array(Model, 'migrater'));`
* Include a `static $db_version = x.x` variable in your class definition.
* Include a `static function migrate($installed_version, $wpdb)` function in your class.
	This function will recieve the current verison of the installed database and the
	$wpdb class as a convenience to cut down on boilerplate code like `global $wpdb`.

Inside your migrate function you should follow the following structure:

* `if (!$installed_version)` - Inside this block you should run your create table statement.
* Then run a series of `if (version_compare('x.x', $installed_version))` statements. This is
	where you can incrementally upgrade the databse table through alter table statements.

Models expose the following api to the user:
* find - the find method takes an attrs array that can have the following keys:
		conditions: an array of conditions for the query
		limit: a number for the limit portion of the query
		offset: a number for the offset
* find_one - like the find method it takes an array of conditions. This method
	only returns one record so it doesn't take a limit or offset.
* create - this method takes an array with the key of params where params is an array
	of key => value pairs where the key is the column name.
* update - this method takes an array with the param key which works like the create
	method and it also takes a conditions key that works like the find/fnd_one methods.
* delete - this method takes an array with the conditions key which works like the
	find/find_one methods.

On your model you can also define a `create_scope` method that takes the params
passed the `create` method. Inside this method you can set any column value that you
want. I use this method to set user_id's so that I don't rely on the js passing
the user id leaving my app open to people passing in user id's that are not them
or setting values that I don't want them to set.

= Creating Controllers =

Controllers are a thin wrapper around the excellent WP_Router plugin from jbrinley. In fact his
plugin is bundled in this plugin so that it can build off of it to build the routes needed.
Controllers are created by extending `BackboneAPIController` and the only requirement is that
this class has a `public static $routes = array()` variable defined on wich is an array of
routes. Each key in this array is the name of the route and it's value will be an array with
the following elements:

* `path` - A regular expression to match agaisnt the url. You can add `()` inside of this
	regexp's that can then be referenced in the params key.
* `params` - You can give names to your captures inside of the path. This is an array of
	names you want for each capture in the order that they appear in the regexp.
* `authorizer` -  This can either be a string with the name of the
	authorizer function, or a key => value array with the key being an http method an the value
	being the name of the authorizing function to use for that method.
* `callback` - This is either a string of the name of the function to call for this method, or
	an array key => value pairs with key being the http method and the value being the name
	of the function to be called.

Once your controller class has been defined you need to instantiate it by creating a new
instance of that class so that everything gets wired up.

There are a couple of controller concepts here that should be explained:

* Authorizers - Authorizers are functions that are called before the actual action. It is
	a chance for you to make sure the user has the ability to actuall call the action. If they
	don't you can use the helper `$this->respond_403` to send back json telling the user
	they don't have access to this method.
* Params - Inside of your controller actions you will have access to `$this->get` and `$this->post`
	variables that will hold what the $_GET and $_POST vars normally contain. The names in this
	array will also be added to the `$this->get` array.
* `$this->post` - Normally when a PUT request is made to php the posted contents are not made
	available to $_POST. BackboneAPIController extracts that away from you and gives you
	access to those values through the `$this->post` array which you should use.
* `$this->respond_404($msg)` - This is a helper function you can call to send a 404 json response. It
	sets the correct headers and sends an error message in the json. It takes and optional message
	argument to override the default error sent.
* `$this->respond_403($msg)` - This is a helper function you can call to send a 403 json response. It
	sets the correct headers and sends an error message in the json. It takes and optional message
	argument to override the default error sent.
* `$this->send_json($payload, $extra_headers)` - This function takes a variable that gets encoded to
	json and sent in the response. It also takes an optional $extra_headers variable which is
	either a string with a single http header or an array of headers to be set before sending the response.

= Creating RESTful Controllers =

Creating controllers is pretty easy but you can write a lot of code over and over just createing
controllers. This is where the RESTful controller comes in. You create a RESTful controller
by extending the BackboneAPIRestfulController class and creating a new instance of it. Each
RESTful controller should contain the following elements:

* `static $resource_slug = ''` - This is the base of your url. For example if you were exposing
	cars as resources this would be cars.
* `static $model_class` = ''` - This is the name that you gave your model class. This model will
	be exposed in your functions by accessing $this->model.

That's it. This controller will expose index, view, create, update, and delete methods. If you
want you can specify which controller actions should be exposed by using one of these two optins:

* Set a `static::$includes` on your class with an array of methods you would like created
* Or set a `static::$exclude` on your class in which you specify which methods you would not
	like to be exposed.

You can override any of the methods created by this controller by defining them in your custom
class. There are some other concepts for the RESTful controller you should understand:

* Scoping methods - These are functions you can use to scope the conditions that get sent to
	the model when doing the index, view, update, and delete actions. There is also a global
	scope method you can define to conditions that get sent to all of them. These are the
	functions you can define:
		`scope` - This will be applied to all of the actions named above.
		`scope_index` - Scopes the index method
		`scope_view` - Scopes the view method
		`scope_update` - Scopes the update method
		`scope_delete` - Scopes the delete method
	These methods should return an array of conditions that can be sent to model.
* `before_*` methods. These are some default authorizers that are called before the various
methods. This is your chance to make sure people have the opportunity to call the action.

Between scopes and the before methods you have things you need to implement access controls.
Scopes allow you to make sure the returned records are scoped to the current user, the
before methods allow you to make sure a user is logged in before sending them on.

== Changelog ==

= 0.1 =

* Initial version