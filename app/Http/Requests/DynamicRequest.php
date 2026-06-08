<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

abstract class DynamicRequest extends FormRequest
{
    public function rules(): array
    {
        $methodName = 'rulesFor' . Str::studly($this->getActionMethod());

        return method_exists($this, $methodName) ? $this->{$methodName}() : [];
    }

    public function messages(): array
    {
        $methodName = 'messagesFor' . Str::studly($this->getActionMethod());

        return method_exists($this, $methodName) ? $this->{$methodName}() : [];
    }

    protected function getActionMethod(): ?string
    {
        return optional($this->route())->getActionMethod();
    }
}
