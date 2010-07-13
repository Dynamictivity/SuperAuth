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
class AclBehavior extends ModelBehavior {
	// row-level acl
	var $userAros = array();

/**
 * Maps ACL type options to ACL models
 *
 * @var array
 * @access protected
 */
	var $__typeMaps = array('requester' => 'Aro', 'controlled' => 'Aco', 'both' => array('Aro', 'Aco'));

/**
 * Sets up the configuation for the model, and loads ACL models if they haven't been already
 *
 * @param mixed $config
 * @return void
 * @access public
 */
	function setup(&$model, $config = array()) {
		if (is_string($config)) {
			$config = array('type' => $config);
		}
		$this->settings[$model->alias] = array_merge(
			array('parentClass' => $model->alias, 'foreignKey' => 'parent_id', 'type' => 'controlled'),
			$config
		);
		$types = $this->__typeMaps[$this->settings[$model->alias]['type']];
		if (!class_exists('AclNode')) {
			require LIBS . 'model' . DS . 'db_acl.php';
		}
		if (!is_array($types)) {
			$types = array($types);
		}
		foreach($types as $type) {
			if (PHP5) {
				$model->{$type} = ClassRegistry::init($type);
			} else {
				$model->{$type} =& ClassRegistry::init($type);
			}
		}
		
		// row-level acl
		$this->model =& $model;
	}
	
	// row-level acl begin
	function beforefind(&$model, $queryData) {
		$types = $this->__typeMaps[strtolower($this->settings[$model->alias]['type'])];
		$types = (array)$types;
		if (in_array('Aco', $types) && array_key_exists('aclConditions', $queryData)) {
			$this->__getUserAros();
			$model->bindModel(
				array(
					'belongsTo' => array(
						'Permissions' => array(
							'className' => 'SuperAuth.PermissionCache',
							'foreignKey' => 'id',
							'fields' => array(
								'id',
								'aro_id',
								'model',
								'foreign_key',
								'_create',
								'_read',
								'_update',
								'_delete'
							)
						)
					)
				), false
			);
			
			$conditions = array(
				'conditions' => array(
					'or' => array(
						'and' => array(
							'Permissions.model' => $model->alias,
							'Permissions._read' => 1,
							'Permissions.aro_id' => $this->userAros,
							$queryData['aclConditions']
						)
					)
				),
				'contain' => array(
					'Permissions'
				)
			);
			unset($queryData['aclConditions']);
			$queryData = Set::merge($queryData, $conditions);
			return $queryData;
		}
	}
	
	private function __getUserAros() {
    	$aros = Classregistry::init('Aro')->find('all',
			array(
				'conditions' => array(
					'Aro.model' => 'User',
					'Aro.foreign_key' => User::get('id')
				),
			    'fields' => array(
					'Aro.id',
					'Aro.model',
					'Aro.foreign_key'
			    )
			)
		);
		
		$this->userAros = Set::extract('/Aro/id', $aros);
	}
    // row-level acl end

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
				trigger_error(__('AclBehavior is setup with more than one type, please specify type parameter for node()', true), E_USER_WARNING);
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
		$types = $this->__typeMaps[strtolower($this->settings[$model->alias]['type'])];
		if (!is_array($types)) {
			$types = array($types);
		}
		foreach ($types as $type) {
			$parent = $model->parentNode();
			if (!empty($parent)) {
				$parent = $this->node($model, $parent, $type);
			}
			$data = array(
				'parent_id' => isset($parent[0][$type]['id']) ? $parent[0][$type]['id'] : null,
				'model' => $model->alias,
				'foreign_key' => $model->id,
				'alias' => isset($model->data[$model->alias][$model->displayField]) ? $model->data[$model->alias][$model->displayField] : null
			);
			if (!$created) {
				$node = $this->node($model, null, $type);
				$data['id'] = isset($node[0][$type]['id']) ? $node[0][$type]['id'] : null;
			}
			$model->{$type}->create();
			$model->{$type}->save($data);
			
			// row-level acl
		 	if ($type == 'Aco' && $created && empty($model->data[$model->alias][$this->settings[$model->alias]['foreignKey']]) && !empty($model->data[$model->alias]['user_id'])) {
				$userAro = ClassRegistry::init('Aro')->find('first', array(
					'conditions' => array(
						'model' => 'User',
						'foreign_key' => $model->data[$model->alias]['user_id']
					)
				));
		 		$ArosAco = ClassRegistry::init('ArosAco');
				$arosAcoData = array(
					'aro_id' => $userAro['Aro']['id'],
					'aco_id' => $model->Aco->id,
					'_create' => true,
					'_read' => true,
					'_update' => true,
					'_delete' => true
				);
				$ArosAco->create();
				$ArosAco->save($arosAcoData);
			}
		}
	}

/**
 * Destroys the ARO/ACO node bound to the deleted record
 *
 * @return void
 * @access public
 */
	function afterDelete(&$model) {
		$types = $this->__typeMaps[strtolower($this->settings[$model->alias]['type'])];
		if (!is_array($types)) {
			$types = array($types);
		}
		foreach ($types as $type) {
			$node = Set::extract($this->node($model, null, $type), "0.{$type}.id");
			if (!empty($node)) {
				$model->{$type}->delete($node);
			}
		}
	}


	function parentNode(&$model) {
		if (!$model->id && empty($model->data)) {
			return null;
		}
		$foreignKey = $this->settings[$model->alias]['foreignKey'];
		$data = $model->data;
		if (empty($data)) {
			$data = $model->read();
		}
		if (!isset($data[$model->alias][$foreignKey])) {
			$data[$model->alias][$foreignKey] = $model->field($foreignKey);
		}
		if (!array_key_exists($foreignKey, $data[$model->alias]) || empty($data[$model->alias][$foreignKey])) {
			return null;
		}
		return array($this->settings[$model->alias]['parentClass'] => array('id' => $data[$model->alias][$foreignKey]));
	}
}