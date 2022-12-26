<?php

namespace FSA\Neuron;

class FilterInput
{
    public function __construct(
        private object &$object,
        private int $type = INPUT_POST
    ) {
    }

    public function inputInteger($param)
    {
        $value = filter_input($this->type, $param, FILTER_VALIDATE_INT);
        if ($value) {
            $this->object->$param = $value;
        }
    }

    public function inputString($param)
    {
        $value = filter_input($this->type, $param);
        if ($value) {
            $this->object->$param = $value;
        }
    }

    public function inputTextarea($param)
    {
        $value = filter_input($this->type, $param);
        if ($value) {
            $this->object->$param = str_replace(["\r\n", "\r"], "\n", $value);
        }
    }

    public function inputDate($param_date, $param_time = null)
    {
        $date = filter_input($this->type, $param_date);
        if ($date) {
            if (isset($param_time)) {
                $time = filter_input($this->type, $param_time);
                if ($time) {
                    $date .= ' ' . $time;
                }
            }
            $this->object->$param_date = $date;
        }
    }

    public function inputDatetime($param)
    {
        $value = filter_input($this->type, $param);
        if ($value) {
            $this->object->$param = $value;
        }
    }

    public function inputCheckbox($param)
    {
        $this->object->$param = filter_input($this->type, $param) == 'on';
    }


    public function inputCheckboxArray($param)
    {
        $values = filter_input($this->type, $param, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
        $this->object->$param = is_array($values) ? array_keys($values) : null;
    }
}
