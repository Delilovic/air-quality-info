<?php
namespace AirQualityInfo\Lib\Form;

class FormElement {

    private static $ruleRegistry;

    private $name;

    private $type;

    private $label;

    private $description;

    private $attributes;

    private $options;

    private $rules = array();

    private $validationMessage;

    private $value;

    private $required;

    private $groupClass = array();

    public function __construct($name, $type, $label, $attributes = array(), $description = null) {
        $this->name = $name;
        $this->type = $type;
        $this->label = $label;
        $this->attributes = $attributes;
        $this->description = $description;
    }

    public function addRule($type, $options = null) {
        if ($type === 'required') {
            $this->required = true;
        }
        $this->rules[] = array('type' => $type, 'options' => $options);
        return $this;
    }

    public function addGroupClass($class) {
        $this->groupClass[] = $class;
        return $this;
    }

    public function setOptions($options) {
        $this->options = $options;
        return $this;
    }

    public function setValue($value) {
        $this->value = $value;
    }

    public function getGroupClass() {
        if (!empty($this->groupClass)) {
            return implode(' ', $this->groupClass);
        }
    }

    public function getEscapedValue() {
        return \AirQualityInfo\Lib\StringUtils::escapeHtmlAttribute($this->value);
    }

    public function getAttributesString() {
        $result = "";
        if ($this->required) {
            $result .= 'required';
        }
        foreach ($this->attributes as $k => $v) {
            $result .= " $k=\"$v\"";
        }
        return $result;
    }

    public function validate($value) {
        $this->value = $value;
        foreach ($this->rules as $rule) {
            if (!$this->validateRule($rule['type'], $rule['options'], $value)) {
                return false;
            }
        }
        return true;
    }

    public function render() {
        require("admin/partials/form/$this->type.php");
    }

    private function validateRule($ruleType, $ruleOptions, $value) {
        $rule = FormElement::getRuleRegistry()->getRule($ruleType);
        if ($rule->validate($value, $ruleOptions, $this->name)) {
            return true;
        }
        
        $msg = $rule->getMessage();
        if (isset($ruleOptions['message'])) {
            $msg = $ruleOptions['message'];
        }
        $this->validationMessage = sprintf(__($msg), __($this->label), ...array_values($ruleOptions));
        return false;
    }

    private static function getRuleRegistry() {
        if (!isset(FormElement::$ruleRegistry)) {
            FormElement::$ruleRegistry = new RuleRegistry();
        }
        return FormElement::$ruleRegistry;
    }
}

?>