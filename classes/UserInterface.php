<?php

namespace FSA\Neuron;

interface UserInterface
{
    static function validate(array $properties): self;
    function getProperties(): array;
    function getId();
    function getLogin(): string;
    function getName(): string;
    function getEmail(): string;
    function getScope(): array;
}
