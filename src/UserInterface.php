<?php

namespace FSA\Neuron;

interface UserInterface
{
    function validate(array $properties): bool;
    function getProperties(): array;
    function getId();
    function getLogin(): string;
    function getName(): string;
    function getEmail(): string;
    function getScope(): ?array;
}
