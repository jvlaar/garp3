<?php
/**
 * @author David Spreekmeester | grrr.nl
 */
class Garp_Spawn_Model_Set extends ArrayObject {
	const ERROR_RELATION_TO_NON_EXISTING_MODEL = "The '%s' model defines a %s relation to unexisting model '%s'.";

	/**
	 * @todo: deze default relations moeten naar de configlaag verplaatst worden.
	 */
	protected $_defaultRelations = array(
		'Author' => array(
			'model' => 'User',
			'type' => 'hasOne',
			'inverse' => false,
			'label' => 'Created by'
		),
		'Modifier' => array(
			'model' => 'User',
			'type' => 'hasOne',
			'inverse' => false,
			'editable' => false,
			'label' => 'Modified by'
		)
	);


	public function __construct(Garp_Spawn_Config_Model_Set $modelSetConfig) {
		foreach ($modelSetConfig as $modelId => $modelConfig) {
			$this[$modelId] = new Garp_Spawn_Model_Base($modelConfig);
		}

		$this->_sortModels();
		$this->_defineDefaultRelations();
		$this->_mirrorHabtmRelationsInSet();
		$this->_mirrorHasManyRelationsInSet();

	}


	public function materializeCombinedBaseModel() {
		$output = '';
		foreach ($this as $model) {
			$output .= $model->renderJsBaseModel($this);
		}

		$modelSetFile = new Garp_Spawn_Js_ModelSet_File_Base();
		$modelSetFile->save($output);
	}
	
	
	public function includeInJsModelLoader() {
		new Garp_Spawn_Js_ModelsIncluder($this);
	}
	
	/**
	 * @param Array &$models Numeric array of Garp_Spawn_Model_Base objects
	 */
	protected function _defineDefaultRelations() {
		foreach ($this as &$model) {
			foreach ($this->_defaultRelations as $defRelName => $defRelParams) {
				if (!count($model->relations->getRelations('name', $defRelName))) {
					$model->relations->add($defRelName, $defRelParams);
				}
			}
		}
	}
	/**
	 * @param Array &$models Numeric array of Garp_Spawn_Model_Base objects
	 */
	protected function _mirrorHasManyRelationsInSet() {
		//	inverse singular relations to multiple relations from the other model
		foreach ($this as $model) {
			$this->_mirrorHasManyRelationsInOpposingModels($model);
		}
	}
	
	protected function _mirrorHasManyRelationsInOpposingModels(Garp_Spawn_Model_Base $model) {
		$singularRelations = $model->relations->getSingularRelations();

		foreach ($singularRelations as $relationName => $relation) {
			if (!$relation->inverse) {
				break;
			}

			$this->_mirrorHasManyRelationsInModel($model, $relationName, $relation);
		}
	}
	
	protected function _mirrorHasManyRelationsInModel(Garp_Spawn_Model_Base $model, $relationName, Garp_Spawn_Relation $relation) {
		$this->_throwErrorIfRelatedModelDoesNotExist($model, $relation);

		$remoteModel = &$this[$relation->model];

		$hasManyRelParams = array(
			'type'			=> 'hasMany',
			'model'			=> $model->id,
			'column'		=> 'id',
			'inline'		=> $relation->inline,
			'editable'		=> $relation->type !== 'belongsTo',
			'weighable'		=> $relation->weighable,
			'oppositeRule'	=> $relationName,
		);

		$remoteModel->relations->add($model->id, $hasManyRelParams, false);		
	}

	/**
	 * @param Array &$models Numeric array of Garp_Spawn_Model_Base objects
	 */
	protected function _mirrorHabtmRelationsInSet() {
		//	inverse singular relations to multiple relations from the other model
		foreach ($this as $model) {
			$this->_mirrorHabtmRelationsInOpposingModels($model);
		}
	}
	
	protected function _mirrorHabtmRelationsInOpposingModels(Garp_Spawn_Model_Base $model) {
		$habtmRelations = $model->relations->getRelations('type', array('hasAndBelongsToMany'));

		foreach ($habtmRelations as $relationName => $relation) {
			$this->_mirrorHabtmRelationsInModel($model, $relationName, $relation);
		}
		
	}
	
	protected function _mirrorHabtmRelationsInModel(Garp_Spawn_Model_Base $model, $relationName, Garp_Spawn_Relation $relation) {
		$this->_throwErrorIfRelatedModelDoesNotExist($model, $relation);

		$habtmRelParams = array(
			'type'			=> 'hasAndBelongsToMany',
			'model'			=> $model->id,
			'column'		=> 'id',
			'inline'		=> $relation->inline,
			'inputs'		=> $relation->inputs,
			'editable'		=> true,
			'weighable'		=> $relation->weighable,
			'oppositeRule'	=> $relationName,
		);

		$remoteModel = &$this[$relation->model];
		$remoteModel->relations->add($model->id, $habtmRelParams, false);
	}
	
	protected function _throwErrorIfRelatedModelDoesNotExist(Garp_Spawn_Model_Base $model, Garp_Spawn_Relation $relation) {
		if (!array_key_exists($relation->model, $this)) {
			$error = sprintf(
				self::ERROR_RELATION_TO_NON_EXISTING_MODEL,
				$model->id,
				$relation->type,
				$relation->model
			);
			throw new Exception($error);
		}
	}
	
	protected function _sortModels() {
		ArrayObject::ksort($this);
	}
}
