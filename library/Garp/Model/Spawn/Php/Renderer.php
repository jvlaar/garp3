<?php
/**
 * Generated PHP model
 * @author David Spreekmeester | grrr.nl
 * @modifiedby $LastChangedBy: $
 * @version $Revision: $
 * @package Garp
 * @subpackage Model
 * @lastmodified $Date: $
 */
class Garp_Model_Spawn_Php_Renderer {
	const _BASE_MODEL_PATH = '/modules/default/models/Base/';
	const _EXTENDED_MODEL_PATH = '/modules/default/models/';
		
	/**
	 * @var Garp_Model_Spawn_Model_Abstract $_model
	 */
	protected $_model;

	/**
	 * Some behaviors defined in the Spawner's model configuration do not need a PHP behavior, and should therefore not be injected into the model as such.
	 */
	protected $_behaviorsExcludedFromRendering = array('Locatable');
	
	protected $_behaviorsThatRequireParams = array('Weighable');
	


	public function __construct(Garp_Model_Spawn_Model_Abstract $model) {
		$this->setModel($model);
	}


	public function save() {
		//	generate base model
		$baseModelPath = $this->_getBaseModelPath($this->_model->id);
		$baseModelContent = $this->_renderBaseModel();
		$this->_saveFile($baseModelPath, $baseModelContent, 'PHP base model', true);

		//	generate extended model
		$extendedModelPath = $this->_getExtendedModelPath($this->_model->id);
		$extendedModelContent = $this->_renderExtendedModel($this->_model->id);
		$this->_saveFile($extendedModelPath, $extendedModelContent, 'PHP extended model', false);

		//	generate hasAndBelongsToMany binding models that relate to this model
		$habtmRelations = $this->_model->relations->getRelations('type', 'hasAndBelongsToMany');
		if ($habtmRelations) {
			foreach ($habtmRelations as $habtmRelation) {
				$bindingModelName = Garp_Model_Spawn_Relations::getBindingModelName($habtmRelation->model, $this->_model->id);
				$bindingBaseModelPath = $this->_getBaseModelPath($bindingModelName);
				$bindingBaseModelContent = $this->_renderBindingBaseModel($this->_model->id, $habtmRelation);
				$this->_saveFile($bindingBaseModelPath, $bindingBaseModelContent, 'PHP base binding model to '.$habtmRelation->model, true);
				
				$bindingExtendedModelPath = $this->_getExtendedModelPath($bindingModelName);
				$bindingExtendedModelContent = $this->_renderExtendedModel($bindingModelName, false);
				$this->_saveFile($bindingExtendedModelPath, $bindingExtendedModelContent, 'PHP extended binding model to '.$habtmRelation->model, false);
			}
		}

		new Garp_Model_Spawn_Php_ModelsIncluder($this->_model->id);
	}
		
	/**
	 * @return Garp_Model_Spawn_Model_Abstract
	 */
	public function getModel() {
		return $this->_model;
	}
	
	/**
	 * @param Garp_Model_Spawn_Model_Abstract $model
	 */
	public function setModel($model) {
		$this->_model = $model;
	}
	
	protected function _saveFile($path, $content, $label, $overwrite = false) {
		if (
			$overwrite ||
			!$overwrite && !file_exists($path)
		) {
			if (!file_put_contents($path, $content)) {
				throw new Exception("Could not generate {$label}.");
			}
		}
	}


	protected static function _getBaseModelPath($modelId) {
		return APPLICATION_PATH.self::_BASE_MODEL_PATH.$modelId.'.php';
	}
	
	
	protected static function _getExtendedModelPath($modelId) {
		return APPLICATION_PATH.self::_EXTENDED_MODEL_PATH.$modelId.'.php';
	}


	/**
	 * Extracts the public properties from an iteratable object
	 */
	protected function _objectToArray($obj) {
		$arr = array();
		$arrObj = is_object($obj) ? get_object_vars($obj) : $obj;

		foreach ($arrObj as $key => $val) {
			if (is_object($val)) {
				if (get_class($val) === 'Garp_Model_Spawn_Relations') {
					$val = $val->getRelations();
				} elseif (get_class($val) === 'Garp_Model_Spawn_Behaviors') {
					$val = $val->getBehaviors();
				} elseif (get_class($val) === 'Garp_Model_Spawn_Fields') {
					$val = $val->getFields();
				}

				$val = $this->_objectToArray($val);
			} elseif (is_array($val)) {
				$val = $this->_objectToArray($val);
			}
			$arr[$key] = $val;
		}

		return $arr;
	}


	protected function _renderBaseModel() {
		$tableName = $this->_getTableName();
		
		$out = $this->_rl("<?php");
		$out.= $this->_rl('/* This file was generated by '.get_class().' */');

		$out.= $this->_rl("class Model_Base_{$this->_model->id} extends Garp_Model_Db {");

		/* Table */
		$out.= $this->_rl("protected \$_name = '{$tableName}';", 1, 2);

		/* Primary */
		$out.= $this->_rl("protected \$_primary = 'id';", 1, 2);

		/* This model's scheme, deducted from the combined Spawn model configurations. */
		$out.= $this->_rl("protected \$_configuration = " . Garp_Model_Spawn_Util::array2phpStatement($this->_objectToArray($this->_model)) .";", 1, 2);

		/* Joint view to include labels of singular relations */
		$out.= $this->_rl("protected \$_jointView = '{$tableName}_joint';", 1, 2);

		/* Default order */
		$orderStatement = (
			strpos($this->_model->order, ",") !== false &&
			strpos($this->_model->order, "(") === false) ?
			Garp_Model_Spawn_Util::array2phpStatement(explode(", ", $this->_model->order)) :
			"'".$this->_model->order."'";
		$out.= $this->_rl("protected \$_defaultOrder = {$orderStatement};", 1, 2);

		/* Relations */
		$relations = $this->_model->relations->getRelations();

		if (count($relations)) {
			/* Bindable */
			$i = 0;
			$out.= $this->_rl('protected $_bindable = array(', 1);

			$registeredBindableModels = array();
			$bindablesOutput = array();

			foreach ($relations as $relationName => $relation) {
				if (!in_array($relation->model, $registeredBindableModels)) {
					$registeredBindableModels[] = $relation->model;
					$bindablesOutput[] = $this->_rl("'Model_{$relation->model}'", 2, 0);
				}
			}
			$bindablesOutput = array_unique($bindablesOutput);
			sort($bindablesOutput);

			$out.= $this->_rl(implode(",\n", $bindablesOutput), 0);
			$out.= $this->_rl(');', 1, 2);
		

			/* ReferenceMap */
			$i = 0;
			$out.= $this->_rl('protected $_referenceMap = array(', 1);

			$referenceMapOutput = array();

			foreach ($relations as $relationName => $relation) {
				if (
					$relation->type === 'hasOne' ||
					$relation->type === 'belongsTo'
				) {
					$referenceMapOutput[] =
						 $this->_rl("'{$relationName}' => array(", 2)
						.$this->_rl("'refTableClass' => 'Model_{$relation->model}',", 3)
						.$this->_rl("'columns' => '{$relation->column}',", 3)
						.$this->_rl("'refColumns' => 'id'", 3)
						.$this->_rl(")", 2, 0)
					;
				}
			}
			
			$out.= $this->_rl(implode(",\n", $referenceMapOutput), 0);

			$out.= $this->_rl(');', 1, 3);
		}

		/* Default behaviors */
		$out.= $this->_rl("public function init() {", 1);
		$out.= $this->_rl('parent::init();', 2);
		$behaviors = $this->_model->behaviors->getBehaviors();
		foreach ($behaviors as $behaviorName => $behavior) {
			if (!in_array($behaviorName, $this->_behaviorsExcludedFromRendering)) {
				$behavior = $this->_filterBehaviorParams($behaviorName, $behavior);
				$behaviorOutput = $this->_renderBehavior($behavior);
				if ($behaviorOutput) {
					$out.= $this->_rl($behaviorOutput, 2);
				}
			}
		}
		$out .= $this->_rl('}', 1, 2);
		
		if (!$this->getModel()->isTranslated()) {
			$out .= $this->_getRecordLabelSql();
		}

		$out .= $this->_rl('}', 0, 0);
		return $out;
	}
	
	/**
	 * Compose the method to fetch composite columns as a string in a MySql query
	 * to use as a label to identify the record. These have to be columns in this table,
	 * to be able to be used flexibly in another query.
	 */
	protected function _getRecordLabelSql() {
		$tableName 				= $this->_getTableName();
		$recordLabelFieldDefs 	= $this->_getRecordLabelFieldDefinitions();
		$labelColumnsListSql 	= implode(', ', $recordLabelFieldDefs);
		$glue 					= $this->_modelHasFirstAndLastNameListFields() ? ' ' : ', ';
		$sql 					= "CONVERT(CONCAT_WS('{$glue}', " . $labelColumnsListSql . ') USING utf8)';

		$out 	= $this->_rl("public function getRecordLabelSql(\$tableAlias = null) {", 1);
		$out 	.= $this->_rl("\$tableAlias = \$tableAlias ?: '{$tableName}';", 2);
		$out 	.= $this->_rl("return \"{$sql}\";", 2);
		$out 	.= $this->_rl('}', 1, 1);
		
		return $out;
	}
	
	protected function _getTableName() {
		$model 			= $this->getModel();
		$tableFactory 	= new Garp_Model_Spawn_MySql_Table_Factory($model);
		$table 			= $tableFactory->produceConfigTable();
		
		return $table->name;
	}
	
	protected function _getRecordLabelFieldDefinitions() {
		$model			= $this->getModel();
		$listFieldNames = $model->fields->listFieldNames;
		$fieldDefs 		= array();

		foreach ($listFieldNames as $listFieldName) {
			try {
				$field = $model->fields->getField($listFieldName);
			} catch (Exception $e) {
				break;
			}

			if (
				!$field ||
				!$field->isSuitableAsLabel()
			) {
				break;
			}

			$fieldDefs[] = $this->_addFieldLabelDefinition($field->name);
		}

		if (!$fieldDefs) {
			$fieldDefs[] = $this->_addFieldLabelDefinition('id');
		}
		
		return $fieldDefs;
	}
	
	protected function _addFieldLabelDefinition($columnName) {
		return "IF(`{\$tableAlias}`.`{$columnName}` <> \\\"\\\", `{\$tableAlias}`.`{$columnName}`, NULL)";
	}		
	
	protected function _modelHasFirstAndLastNameListFields() {
		return (
			$this->_model->fields->getFields('name', 'first_name') &&
			$this->_model->fields->getFields('name', 'last_name')
		);
	}


	/**
	 * @return Garp_Model_Spawn_Behavior The filtered behavior.
	 */
	protected function _filterBehaviorParams($behaviorName, Garp_Model_Spawn_Behavior_Type_Abstract $behavior) {
		$filteredBehavior = clone $behavior;

		if ($behaviorName === 'Weighable') {
			$habtmRels		= $this->_model->relations->getRelations('type', 'hasAndBelongsToMany');
			$habtmRelNames 	= array_keys($habtmRels);
			$params			= $filteredBehavior->getParams();

			foreach ($params as $modelName => $paramValue) {
				if (in_array($modelName, $habtmRelNames)) {
					unset($params[$modelName]);
				}
			}
		}

		return $filteredBehavior;
	}


	protected function _renderExtendedModel($modelId, $dynamicBase = true) {
		$out = $this->_rl("<?php");

		$extendsFrom =
			(
				($dynamicBase && $this->_model->module === 'garp') ? 'G_Model_' : 'Model_Base_'
			)
			. $modelId
		;
		$out.= $this->_rl("class Model_{$modelId} extends {$extendsFrom} {", 0);
		$out.= $this->_rl("public function init() {", 1);
		$out.= $this->_rl('parent::init();', 2);
		$out.= $this->_rl('}', 1);
		$out.= $this->_rl("}", 0, 0);
		return $out;
	}
	
	
	protected function _renderBindingBaseModel($modelId1, Garp_Model_Spawn_Relation $habtmRelation) {
		$bindingModel	= $habtmRelation->getBindingModel();

		$tableFactory 	= new Garp_Model_Spawn_MySql_Table_Factory($bindingModel);
		$table 			= $tableFactory->produceConfigTable();

		$modelId2 		= $habtmRelation->model;
		$isHomophile 	= $modelId1 === $modelId2;
		$modelColumn1 	= Garp_Model_Spawn_Relations::getRelationColumn($modelId1, $isHomophile ? 1 : null);
		$modelColumn2 	= Garp_Model_Spawn_Relations::getRelationColumn($modelId2, $isHomophile ? 2 : null);

		$alphabeticModelIds = array($modelId1, $modelId2);
		sort($alphabeticModelIds);
		$alphabeticModelColumns = $alphabeticModelIds[0] === $modelId1 ?
			array($modelColumn1, $modelColumn2) :
			array($modelColumn2, $modelColumn1)
		;

		$out = $this->_rl('<?php');
		$out.= $this->_rl('/* This file was generated by '.get_class().' */');
		$out.= $this->_rl("class Model_Base_{$bindingModel->id} extends Garp_Model_Db {");

		$out.= $this->_rl("protected \$_name = '{$table->name}';", 1, 2);

		$out.= $this->_rl("protected \$_bindable = array('Model_{$alphabeticModelIds[0]}'"
			.(!$isHomophile ? ", 'Model_{$alphabeticModelIds[1]}'" : '')
			.");", 1, 2)
		;

		$out.= $this->_rl('protected $_referenceMap = array(', 1);
		$out.= $this->_rl("'{$alphabeticModelIds[0]}".($isHomophile ? '1' : '')."' => array(", 2);
		$out.= $this->_rl("'columns' => '{$alphabeticModelColumns[0]}',", 3);
		$out.= $this->_rl("'refTableClass' => 'Model_{$alphabeticModelIds[0]}',", 3);
		$out.= $this->_rl("'refColumns' => 'id'", 3);
		$out.= $this->_rl("),", 2);

		$out.= $this->_rl("'{$alphabeticModelIds[1]}".($isHomophile ? '2' : '')."' => array(", 2);
		$out.= $this->_rl("'columns' => '{$alphabeticModelColumns[1]}',", 3);
		$out.= $this->_rl("'refTableClass' => 'Model_{$alphabeticModelIds[1]}',", 3);
		$out.= $this->_rl("'refColumns' => 'id'", 3);
		$out.= $this->_rl(")", 2);
		$out.= $this->_rl(");", 1, 2);

		$out.= $this->_rl("public function init() {", 1);
		$out.= $this->_rl("parent::init();", 2);

		if ($habtmRelation->weighable) {
			$model1FieldName = Garp_Model_Spawn_Util::camelcased2underscored($modelId1);
			$model2FieldName = Garp_Model_Spawn_Util::camelcased2underscored($modelId2);
			$postFix1 = $isHomophile ? '1' : '';
			$postFix2 = $isHomophile ? '2' : '';
			
			$combinedColumnA = $model1FieldName . $postFix1 . '_' . $model2FieldName . $postFix2;
			$combinedColumnB = $model2FieldName . $postFix2 . '_' . $model1FieldName . $postFix1;

			$out.= "\n";
			$out.= $this->_rl("\$this->registerObserver(new Garp_Model_Behavior_Weighable(array(", 2);
			$out.= $this->_rl("'{$modelId1}{$postFix1}' => array(", 3);
			$out.= $this->_rl("'foreignKeyColumn' => '{$modelColumn1}',", 4);
			$out.= $this->_rl("'weightColumn' => '{$combinedColumnA}_weight'", 4);
			$out.= $this->_rl("),", 3);
			$out.= $this->_rl("'{$modelId2}{$postFix2}' => array(", 3);
			$out.= $this->_rl("'foreignKeyColumn' => '{$modelColumn2}',", 4);
			$out.= $this->_rl("'weightColumn' => '{$combinedColumnB}_weight'", 4);
			$out.= $this->_rl(")", 3);
			$out.= $this->_rl(")));", 2, 1);
		}
		
		$out.= $this->_rl("}", 1);

		$out.= $this->_rl("}", 0);

		return $out;
	}


	protected function _renderBehavior(Garp_Model_Spawn_Behavior_Type_Abstract $behavior) {
		$params = $behavior->getParams();
		$name	= $behavior->getName();
		$type	= $behavior->getType();

		if (
			$params ||
			!in_array($name, $this->_behaviorsThatRequireParams)
		) {
			$paramsString = is_array($params) ?
				Garp_Model_Spawn_Util::array2phpStatement($params) :
				null
			;
			return "\$this->registerObserver(new Garp_Model_{$type}_{$name}({$paramsString}));";
		}
	}


	/**
	 * Render line with tabs and newlines
	 */
	protected function _rl($content, $tabs = 0, $newlines = 1) {
		return str_repeat("\t", $tabs).$content.str_repeat("\n", $newlines);
	}
}
