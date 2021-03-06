<?php 

namespace Yaro\Jarboe\Fields;

use Yaro\Jarboe\Exceptions\JarboePreValidationException;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;


abstract class AbstractField 
{

    protected $fieldName;
    protected $attributes;
    protected $options;
    protected $definition;

    protected $handler;


    public function __construct($fieldName, $attributes, $options, $definition, $handler)
    {
        $this->attributes = $this->_prepareAttributes($attributes);
        $this->options    = $options;
        $this->definition = $definition;
        $this->fieldName  = $fieldName;
        
        $this->handler = &$handler;
        
        $this->onAssets();
    } // end __construct
    
    protected function onAssets() {} // end onAssets
    
    public function getDefinitionOption($ident, $default = null)
    {
        $value = array_get($this->definition, $ident);
        if (!$value && !is_null($default)) {
            return $default;
        }
        
        return $value;
    } // end getDefinitionOption
    
    public function isPattern()
    {
        return false;
    } // end isPattern
    
    public function isShowRawListValue()
    {
        return false;
    } // end isShowRawListValue
    
    public function isInlineEdit()
    {
        return $this->getAttribute('is_inline');
    } // end isInlineEdit

    public function getFieldName()
    {
        return $this->fieldName;
    } // end getFieldName

    private function _prepareAttributes($attributes)
    {
        // TODO:
        $attributes['fast-edit'] = isset($attributes['fast-edit']) && $attributes['fast-edit'];
        $attributes['filter'] = isset($attributes['filter']) ? $attributes['filter'] : false;
        $attributes['hide'] = isset($attributes['hide']) ? $attributes['hide'] : false;
        $attributes['is_null'] = isset($attributes['is_null']) ? $attributes['is_null'] : false;

        return $attributes;
    } // end _prepareAttributes

    protected function getOption($ident)
    {
        return $this->options[$ident];
    } // end getOption

    public function getAttribute($ident, $default = false)
    {
        return array_key_exists($ident, $this->attributes) ? $this->attributes[$ident] : $default;
    } // end getAttribute
    
    public function getRequiredAttribute($ident)
    {
        if (!array_key_exists($ident, $this->attributes)) {
            throw new \RuntimeException('Field ['. $this->getFieldName() .'] requires ['. $ident .'] attribute');
        }
        
        return $this->attributes[$ident];
    } // end getAttribute
    
    public function isHidden()
    {
        return $this->getAttribute('hide');
    } // end isHidden

    public function getValue($row, $postfix = '')
    {
        if (!is_object($row)) {
            $row = (object) $row;
        }
        
        if ($this->hasCustomHandlerMethod('onGetValue')) {
            $res = $this->handler->onGetValue($this, $row, $postfix);
            if ($res) {
                return $res;
            }
        }
        
        $fieldName = $this->getFieldName() . $postfix;
        // postfix used for getting values for form - tabs loop
        // so there is no need to force appending postfix
        if ($this->getAttribute('tabs') && !$postfix) {
            $tabs = $this->getAttribute('tabs');
            $fieldName = $fieldName . $tabs[0]['postfix'];
        }
        $value = isset($row->$fieldName) ? $row->$fieldName : '';
        
        return $value;
    } // end getValue
    
    public function getExportValue($type, $row, $postfix = '')
    {
        if ($this->hasCustomHandlerMethod('onGetExportValue')) {
            $res = $this->handler->onGetExportValue($this, $type, $row, $postfix);
            if ($res) {
                return $res;
            }
        }
        
        $value = $this->getValue($row, $postfix);
        // cuz double quotes is escaping by more double quotes in csv
        $escapedValue = preg_replace('~"~', '""', $value);
        
        return $escapedValue;
    } // end getExportValue

    public function getListValue($row)
    {
        if ($this->hasCustomHandlerMethod('onGetListValue')) {
            $res = $this->handler->onGetListValue($this, $row);
            if ($res) {
                return $res;
            }
        }
        
        return $this->getValue($row);
    } // end getListValue

    public function getEditInput($row = array())
    {
        if ($this->hasCustomHandlerMethod('onGetEditInput')) {
            $res = $this->handler->onGetEditInput($this, $row);
            if ($res) {
                return $res;
            }
        }

        $type = $this->getAttribute('type');

        $input = view('admin::tb.input.'. $type);
        $input->value = $this->getValue($row);
        $input->name  = $this->getFieldName();
        $input->rows  = $this->getAttribute('rows');
        $input->mask  = $this->getAttribute('mask');
        $input->placeholder = $this->getAttribute('placeholder');

        return $input->render();
    } // end getEditInput
    
    public function getInlineEditInput($row)
    {
        return '';
    } // end getInlineEditInput
    
    public function getTabbedEditInput($row = array())
    {
        if ($this->hasCustomHandlerMethod('onGetTabbedEditInput')) {
            $res = $this->handler->onGetTabbedEditInput($this, $row);
            if ($res) {
                return $res;
            }
        }
        
        $type = $this->getAttribute('type');
        
        $input = view('admin::tb.input.tab.'. $type);
        $input->value = $this->getValue($row);
        $input->name  = $this->getFieldName();
        $input->rows  = $this->getAttribute('rows');
        $input->mask  = $this->getAttribute('mask');
        $input->placeholder = $this->getAttribute('placeholder');
        $input->caption = $this->getAttribute('caption');
        $input->tabs = $this->getPreparedTabs($row);
        // HACK: for tabs right behaviour in edit-create modals
        $input->pre = $row ? 'e' : 'c';
        
        
        return $input->render();
    } // end getTabbedEditInput
    
    protected function getPreparedTabs($row)
    {
        $tabs = $this->getAttribute('tabs');
        $required = array(
            'placeholder',
            'postfix'
        );
        foreach ($tabs as &$tab) {
            foreach ($required as $option) {
                if (!isset($tab[$option])) {
                    $tab[$option] = '';
                }
            }
            
            $tab['value'] = $this->getValue($row, $tab['postfix']);
        }
        
        return $tabs;
    } // end getPreparedTabs

    public function getFilterInput()
    {
        if (!$this->getAttribute('filter')) {
            return '';
        }

        // FIXME: def_name
        $definitionName = $this->definition->getName();
        $sessionPath = 'table_builder.'.$definitionName.'.filters.'.$this->getFieldName();
        $filter = session()->get($sessionPath, '');

        $type = $this->getAttribute('filter');

        $input = view('admin::tb.filter.'. $type);
        $input->name = $this->getFieldName();
        $input->value = $filter;

        return $input->render();
    } // end getFilterInput

    protected function hasCustomHandlerMethod($methodName)
    {
        return $this->handler && is_callable(array($this->handler, $methodName));
    } // end hasCustomHandlerMethod

    public function prepareQueryValue($value)
    {
        if (!$value) {
            if ($this->getAttribute('is_null')) {
                return null;
            }
        }

        return $value;
    } // end prepareQueryValue

    public function onSelectValue(&$db)
    {
        if ($this->hasCustomHandlerMethod('onAddSelectField')) {
            $res = $this->handler->onAddSelectField($this, $db);
            if ($res) {
                return $res;
            }
        }
        
        $tabs = $this->getAttribute('tabs');
        if ($tabs) {
            foreach ($tabs as $tab) {
                $name = $this->definition['db']['table'] .'.'. $this->getFieldName() . $tab['postfix'];
                $db->addSelect($name);
            }
        } else {
            $db->addSelect($this->definition->getDatabaseOption('table') .'.'. $this->getFieldName());
        }
    } // end onSelectValue
    
    public function isReadonly()
    {
        return false;
    } // end isReadonly
    
    public function getClientsideValidatorRules()
    {
        $validation = $this->getAttribute('validation');
        if (!isset($validation['client'])) {
            return;
        }
        $validation = $validation['client'];
        
        $rules = isset($validation['rules']) ? $validation['rules'] : array();
        $name  = $this->getFieldName();
        $tabs  = $this->getAttribute('tabs');
        
        $data = compact('rules', 'name', 'tabs');
        return View::make('admin::tb.validator_rules', $data)->render();
    } // end getClientsideValidatorRules
    
    public function getClientsideValidatorMessages()
    {
        $validation = $this->getAttribute('validation');
        if (!isset($validation['client'])) {
            return;
        }
        $validation = $validation['client'];
        
        $messages = isset($validation['messages']) ? $validation['messages'] : array();
        $name     = $this->getFieldName();
        $tabs     = $this->getAttribute('tabs');
        
        $data = compact('messages', 'name', 'tabs');
        return View::make('admin::tb.validator_messages', $data)->render();
    } // end getClientsideValidatorMessages
    
    public function doValidate($value)
    {
        $validation = $this->getAttribute('validation');
        if (!isset($validation['server'])) {
            return;
        }
        
        $rules = $validation['server']['rules'];
        $messages = isset($validation['server']['messages']) ? $validation['server']['messages'] : array();
        $name = $this->getFieldName();
        
        $validator = Validator::make(
            array(
                $name => $value,
            ),
            array(
                $name => $rules,
            ),
            $messages
        );
        
        if ($validator->fails()) {
            $errors = implode('|', $validator->messages()->all());
            throw new JarboePreValidationException($errors);
        }
    } // end doValidate
    
    public function getSubActions()
    {
        return '';
    } // end getSubActions
    
    public function getLabelClass()
    {
        return 'input';
    } // end getLabelClass

    public function isEditable()
    {
        return true;
    } // end isEditable

    public function getRowColor($row)
    {
        return '';
    } // end getRowColor
    
    public function afterInsert($id, $values) {} // end afterInsert
    
    public function afterUpdate($id, $values) {} // end afterUpdate

    abstract public function onSearchFilter(&$db, $value);
    
    public function doSaveInlineEditForm($idRow, $values)
    {
        throw new \RuntimeException('Not implemented yet');
    } // end doSaveInlineEditForm
    
}
