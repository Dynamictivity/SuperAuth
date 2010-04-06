<?php
/**
 * ACL behavior class.
 *
 * Enables objects to easily tie into an ACL system
 *
 * PHP versions 4 and 5
 *
 * CakePHP :  Rapid Development Framework (http://cakephp.org)
 * Copyright 2006-2010, Cake Software Foundation, Inc.
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2006-2010, Cake Software Foundation, Inc.
 * @link          http://cakephp.org CakePHP Project
 * @package       cake
 * @subpackage    cake.cake.libs.model.behaviors
 * @since         CakePHP v 1.2.0.4487
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

/**
 * Short description for file
 *
 * @package       cake
 * @subpackage    cake.cake.libs.model.behaviors
 */
class SuperAclBehavior extends ModelBehavior {

/**
 * Maps ACL type options to ACL models
 *
 * @var array
 * @access protected
 */
	var $__typeMaps = array('requester' => 'Aro', 'controlled' => 'Aco', 'both' => array('Aco', 'Aro'));

/**
 * Sets up the configuation for the model, and loads ACL models if they haven't been already
 *
 * @param mixed $config
 * @return void
 * @access public
 */
	function setup(&$model, $config = array()) {
		if (is_array($config) && !empty($config)) {
			$config = 'both';
		}
		if (is_string($config)) {
			$config = array('type' => $config);
		}
		$this->settings[$model->alias] = array_merge(array('type' => 'requester'), $config);
		$types = (array)$this->__typeMaps[$this->settings[$model->alias]['type']];
		if (!class_exists('AclNode')) {
			require LIBS . 'model' . DS . 'db_acl.php';
		}
		foreach($types as $type) {
			if (PHP5) {
				$model->{$type} = ClassRegistry::init($type);
			} else {
				$model->{$type} =& ClassRegistry::init($type);
			}
		}
		if (!method_exists($model, 'parentNode')) {
			trigger_error(sprintf(__('Callback parentNode() not defined in %s', true), $model->alias), E_USER_WARNING);
		}
	}

/**
 * Retrieves the Aro/Aco node for this model
 *
 * @param mixed $ref
 * @return array
 * @access public
 */
	function node(&$model, $ref = null, $type = null) {
		if (empty($type)) {
			$type = $this->__typeMaps[strtolower($this->settings[$model->alias]['type'])];
			if (is_array($type)) {
				trigger_error(__('AclBehavior is setup with more then one type, please specify type parameter for node()', true), E_USER_WARNING);
				return null;
			}
		}
		if (empty($ref)) {
			$ref = array('model' => $model->alias, 'foreign_key' => $model->id);
		}
		return $model->{$type}->node($ref);
	}

/**
 * Creates a new ARO/ACO node bound to this record
 *
 * @param boolean $created True if this is a new record
 * @return void
 * @access public
 */
	function afterSave(&$model, $created) {
		$types = (array)$this->__typeMaps[strtolower($this->settings[$model->alias]['type'])];
		foreach ($types as $type) {
			$parent = $model->parentNode();
			if (!empty($parent)) {
				$parent = $this->node($model, $parent, $type);
			}
			$data = array(
				'parent_id' => isset($parent[0][$type]['id']) ? $parent[0][$type]['id'] : null,
				'model' => $model->alias,
				'foreign_key' => $model->id
			);
			if (!$created) {
				$node = $this->node($model, null, $type);
				$data['id'] = isset($node[0][$type]['id']) ? $node[0][$type]['id'] : null;
			}
			$model->{$type}->create();
			$model->{$type}->save($data);
		}
	}

/**
 * Destroys the ARO/ACO node bound to the deleted record
 *
 * @return void
 * @access public
 */
	function afterDelete(&$model) {
		$types = (array)$this->__typeMaps[strtolower($this->settings[$model->alias]['type'])];
		foreach ($types as $type) {
			$node = Set::extract($this->node($model, null, $type), "0.{$type}.id");
			if (!empty($node)) {
				$model->{$type}->delete($node);
			}
		}
	}
}

?>