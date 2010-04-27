# SuperAuth

## Introduction
SuperAuth is a plugin which acts as an extension to the core cakePHP authentication and acl behaviors and components.
Currently it supports full row-level acl, among a few other goodies.

<b>If you are not already using ACL in your cakePHP app, read the</b> [Cook Book](http://book.cakephp.org/view/1242/Access-Control-Lists)

## Current Features
 * Completely automagic row-level ACL (This took me a solid 6 months to perfect)
 * Remember me functionality (Inspiration from Google and The Bakery)
 * actsAs both Controlled(ACO) and Requester(ARO) (Thanks Ceeram)
 * parentNode() in the behavior by default, configurable via actsAs array, can be overidden in the model (Thanks Ceeram)
 * Permission caching - allows for quick permission-based queries (Thanks AD7Six)
 * Automagic aros_acos rows created for user_id passed in model data
 * Support for user belonging to multiple groups (currently untested)
 * Permissions returned with query in $results['Permissions'] (used to determine whether to show/hide crud links)
 * Automatic crud fallback if a row-id is not passed via the url

## Additional Information
 * PHP5
 * Should work with CakePHP 1.2 but untested
 
## Todo
 * If user doesn't have "create" access, don't let them create child acos
 * Permission management interface
 * Optimize when permission caching happens, instead of on every page load
 * When auto-creating permissions on record creation, check parent permissions if they exist and apply those permissions instead of full access (optionally)

## Issues
 * No current permission management interface. (You have to DIY)
 * PermissionCache is updated on every page load, instead of only on permission changes
 * Other unknown potential issues. (Please let me know)

## Installation Instructions
 1.	Create a folder in /app/plugins/ called /super_auth/
 2.	Drop everything into your /app/plugins/super_auth/ folder

### Setup Your Controllers
The following is an example app_controller

	class AppController extends Controller {
	
	    var $components = array(
	    	'SuperAuth.Auth' => array(
	    		'authorize' => 'actions',
	    		'actionPath' => 'controllers/',
	    		'allowedActions' => array('display')
	    	),
			'SuperAuth.Acl',
	    	'Session'
	    );
	
	    var $helpers = array(
			'Session'
	    );
	    
	}

Make sure to add SuperAuth.Auth in your components array before SuperAuth.Acl, or this system won't work properly.

### Initialize Row-Level ACL Automatically
Configure SuperAuth.Auth like so in the controllers you want to use row-level ACL (i.e. Posts Controller)

	class PostsController extends Controller {
	
		function beforeFilter() {
			$this->Auth->authorize = 'acl';
			parent::beforeFilter();
		}
		
	}

If you want to make it a bit more automated, as an example I have added this to my app_controller:

	function beforeFilter() {
		if ($this->{$this->modelClass}->Behaviors->attached('SuperAuth.Acl')) {
			$this->Auth->authorize = 'acl';
		}
	}
	
What the above code does is initialize row-level ACL automatically when a model has the acl behavior attached. You do not need to have the row-level ACL magic working on models that do not require it. It could potentially cause problems, or slow down your application.
 
### Initialize Database Tables

Run the following queries in MySQL:
 
	ALTER TABLE  `aros_acos` ADD UNIQUE (
	`aro_id` ,
	`aco_id`
	);
 
	CREATE TABLE IF NOT EXISTS `permission_cache` (
	  `id` bigint(20) NOT NULL AUTO_INCREMENT,
	  `aro_id` int(11) DEFAULT NULL,
	  `aco_id` int(11) DEFAULT NULL,
	  `model` varchar(255) DEFAULT NULL,
	  `foreign_key` int(11) DEFAULT NULL,
	  `_create` tinyint(1) DEFAULT NULL,
	  `_read` tinyint(1) DEFAULT NULL,
	  `_update` tinyint(1) DEFAULT NULL,
	  `_delete` tinyint(1) DEFAULT NULL,
	  `rule_id` int(11) DEFAULT NULL,
	  `rule_aro_id` int(11) DEFAULT NULL,
	  `rule_aco_id` int(11) DEFAULT NULL,
	  `created` datetime DEFAULT NULL,
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

### Setting Up Your Models
The following is an example of what you would put in your user model, where user belongsTo group

	var $actsAs = array('SuperAuth.Acl' => array('type' => 'requester', 'parentClass'=> 'Group', 'foreignKey' => 'group_id'));

<em>Options are "controlled", "requester" or "both"</em>
 
### Doing row-level-queries
The following is an example conditions array for all records the user has at least read access on
 
	array(
		'conditions' => array(), // put your array of custom conditions here
		'aclConditions' => array() // only find records the user has "read" permissions on (default)
	)
	
The following is an example conditions array for all records the user has update access on
 
	array(
		'conditions' => array(), // put your array of custom conditions here
		'aclConditions' => array('Permissions._update' => true) // only find records the user has "read+update" permissions on
	)
	
### Using Returned Permissions In View Layer
When you do a query using row-level ACL, the permissions are returned in the results under the associated "Permissions" model. (Note the Permissions model name is plural to avoid conflicts)

For instance, to decide whether or not to show an edit link, you can do something like this:

	if ($post['Permissions']['_update']) {
		echo $this->Html->link(__('Edit', true), array('action' => 'edit', $post['Post']['id']));
	}
	
## Using Remember Me Functionality
All you need to do to activate this is include a remember_me checkbox in your login form, and everything else is magic!

## Conclusion
That is the basic implementation for now, there is more to come. I am constantly working on this because I have a very permissions intensive application, and that is why I have had to develop all of this. If you have any feedback, questions, comments, contributions, updates, etc - please let me know. I would love to know what is right/wrong and what and how things can be improved.