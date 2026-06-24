<?php

namespace RaiseStudio\Import\Fields;

class Field
{
    public function __construct(
        protected string $name,
        protected string $label = '',
        protected array $rules = [],
        protected mixed $default = null,
        protected ?array $options = null,
        protected bool $required = false,
    ) {}

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label ?: $this->name;
    }

    public function rules(array|string $rules): self
    {
        $this->rules = is_string($rules) ? explode('|', $rules) : $rules;

        return $this;
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;

        return $this;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function options(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function getOptions(): ?array
    {
        return $this->options;
    }

    public function required(bool $required = true): self
    {
        $this->required = $required;

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }
}
