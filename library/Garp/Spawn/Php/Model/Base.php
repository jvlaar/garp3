<?php
/**
 * Generated PHP model
 * @author David Spreekmeester | grrr.nl
 * @package Garp
 * @subpackage Spawn
 */
class Garp_Spawn_Php_Model_Base extends Garp_Spawn_Php_Model_Abstract {
    const MODEL_DIR = '/modules/default/Model/Base/';

    protected $_behaviorsThatRequireParams = array('Weighable');


    public function getPath() {
        $model = $this->getModel();
        return APPLICATION_PATH . self::MODEL_DIR . $model->id . '.php';
    }

    public function isOverwriteEnabled() {
        return true;
    }

    public function render() {
        $tableName  = $this->getTableName();
        $model      = $this->getModel();

        $out = $this->_rl("<?php");
        $out .= $this->_rl('/* This file was generated by '. get_class() .' - do not edit */');

        $out .= $this->_rl("class Model_Base_{$model->id} extends Garp_Model_Db {");

        /* Table */
        $out .= $this->_rl("protected \$_name = '{$tableName}';", 1, 2);

        /* Primary */
        $out .= $this->_rl("protected \$_primary = 'id';", 1, 2);

        /* Unilingual model */
        if (get_class($model) === 'Garp_Spawn_Model_I18n') {
            $coreModelId = substr($model->id, 0, -4);
            $out .= $this->_rl("protected \$_unilingualModel = 'Model_$coreModelId';", 1, 2);
        }

        /* This model's scheme, deducted from the combined Spawn model configurations. */
        $out .= $this->_renderFlattenedConfiguration();

        /* List fields */
        $listFields = $model->fields->getListFieldNames();
        $listFieldsArrayScript = Garp_Spawn_Util::array2phpStatement($listFields);
        $out .= $this->_rl("protected \$_listFields = $listFieldsArrayScript;", 1, 2);

        /* Joint view to include labels of singular relations */
        $jointViewProperty = $this->_renderJointViewProperty();
        $out.= $jointViewProperty;

        /* Default order */
        $defaultOrder = $this->_renderDefaultOrder();
        $out .= $defaultOrder;

        /* Relations */
        $relations = $this->_renderRelations();
        $out .= $relations;

        /* Default behaviors */
        $behaviors = $this->_renderBehaviors();
        $out .= $behaviors;

        $out .= $this->_rl('}', 0, 0);
        return $out;
    }

    protected function _renderBehaviors() {
        $behaviors = $this->getModel()->behaviors->getBehaviors();

        $out  = $this->_rl("public function init() {", 1);
        $out .= $this->_rl('parent::init();', 2);

        foreach ($behaviors as $behaviorName => $behavior) {
            $out .= $this->_renderBehaviorNeedingPhpModelObserver($behavior);
        }

        $out .= $this->_rl('}', 1, 2);

        return $out;
    }

    protected function _renderBehaviorNeedingPhpModelObserver(Garp_Spawn_Behavior_Type_Abstract $behavior) {
        if (!$behavior->needsPhpModelObserver()) {
            return;
        }

        $behaviorOutput = $this->_renderBehavior($behavior);
        $out            = $this->_rl($behaviorOutput, 2);

        return $out;
    }

    protected function _renderBehavior(Garp_Spawn_Behavior_Type_Abstract $behavior) {
        $module = $behavior->getModule();
        $name   = $behavior->getName();
        $type   = $behavior->getType();
        $params = $name === 'Weighable' ?
            $behavior->getNonHabtmParams() :
            $behavior->getParams()
        ;

        if (
            $params ||
            !in_array($name, $this->_behaviorsThatRequireParams)
        ) {
            $paramsString = is_array($params) ?
                Garp_Spawn_Util::array2phpStatement($params) :
                null
            ;
            return "\$this->registerObserver(new {$module}_Model_{$type}_{$name}({$paramsString}));";
        }
    }

    protected function _renderRelations() {
        $model      = $this->getModel();
        $relations  = $model->relations->getRelations();

        if (!count($relations)) {
            return;
        }

        /* Bindable */
        $out = $this->_renderBindable();

        /* ReferenceMap */
        $out .= $this->_renderReferenceMap();

        return $out;
    }

    protected function _renderBindable() {
        $model      = $this->getModel();
        $relations  = $model->relations->getRelations();

        $bindableModelNames = $this->_getBindableModelNames();
        $bindablesLines     = array();

        foreach ($bindableModelNames as $modelName) {
            $bindableLines[] = $this->_rl("'Model_{$modelName}'", 2, 0);
        }

        $bindablesOutput = implode(",\n", $bindableLines);

        $out = $this->_rl('protected $_bindable = array(', 1);
        $out .= $this->_rl($bindablesOutput, 0);
        $out .= $this->_rl(');', 1, 2);

        return $out;
    }

    protected function _renderReferenceMap() {
        $relations  = $this->getModel()->relations->getSingularRelations();
        $references = array();

        foreach ($relations as $relationName => $relation) {
            $references[] = $this->_renderReferenceMapEntry($relationName, $relation);
        }

        $referencesOutput = implode(",\n", $references);

        $out  = $this->_rl('protected $_referenceMap = array(', 1);
        $out .= $this->_rl($referencesOutput, 0);
        $out .= $this->_rl(');', 1, 3);

        return $out;
    }

    protected function _renderReferenceMapEntry($relationName, Garp_Spawn_Relation $relation) {
        $entry =
              $this->_rl("'{$relationName}' => array(", 2)
            . $this->_rl("'refTableClass' => 'Model_{$relation->model}',", 3)
            . $this->_rl("'columns' => '{$relation->column}',", 3)
            . $this->_rl("'refColumns' => 'id'", 3)
            . $this->_rl(")", 2, 0)
        ;

        return $entry;
    }

    protected function _renderFlattenedConfiguration() {
        $model              = $this->getModel();
        $modelArray         = $this->_convertToArray($model);
        $modelArrayScript   = Garp_Spawn_Util::array2phpStatement($modelArray);
        return $this->_rl("protected \$_configuration = " . $modelArrayScript .";", 1, 2);
    }

    protected function _getBindableModelNames() {
        $relations  = $this->getModel()->relations->getRelations();
        $modelNames = array();

        foreach ($relations as $relation) {
            $modelNames[] = $relation->model;
        }

        $modelNames = array_unique($modelNames);
        sort($modelNames);

        return $modelNames;
    }

    protected function _renderJointViewProperty() {
        $tableName  = $this->getTableName();
        $prop       = "protected \$_jointView = '{$tableName}_joint';";
        $out        = $this->_rl($prop, 1, 2);

        return $out;
    }

    protected function _renderDefaultOrder() {
        $model                          = $this->getModel();
        $commaInOrder                   = strpos($model->order, ",") !== false;
        $noOpeningParenthesisInOrder    = strpos($model->order, "(") === false;
        $orderFieldNames                = explode(", ", $model->order);
        $orderFieldNamesStatement       = Garp_Spawn_Util::array2phpStatement($orderFieldNames);

        $orderValueStatement = (
            $commaInOrder &&
            $noOpeningParenthesisInOrder
        ) ?
            $orderFieldNamesStatement :
            "'{$model->order}'"
        ;

        $orderStatement = "protected \$_defaultOrder = {$orderValueStatement};";
        $out            = $this->_rl($orderStatement, 1, 2);

        return $out;
    }

    /**
     * Extracts the public properties from an iteratable object
     * @param Mixed $obj    At first, feed this a Garp_Spawn_Model_Abstract, after which
     *                      it calls itself with an array.
     */
    protected function _convertToArray($obj) {
        $arr    = array();
        $arrObj = is_object($obj) ? get_object_vars($obj) : $obj;

        foreach ($arrObj as $key => $val) {
            if (is_object($val)) {
                switch (get_class($val)) {
                    case 'Garp_Spawn_Relation_Set':
                        $val = $val->getRelations();
                    break;
                    case 'Garp_Spawn_Behaviors':
                        $val = $val->getBehaviors();
                    break;
                    case 'Garp_Spawn_Fields':
                        $val = $val->getFields();
                }

                $val = $this->_convertToArray($val);
            } elseif (is_array($val)) {
                $val = $this->_convertToArray($val);
            }
            $arr[$key] = $val;
        }

        return $arr;
    }
}
