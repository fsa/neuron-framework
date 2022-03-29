<?php

namespace FSA\Neuron;

interface UserInterface
{
    static function login($login, $password): ?self;
    public function getConstructorArgs();
    public function validate();
    public function getId();
    public function getLogin();
    public function getName();
    public function getEmail();
    public function getScope();
}
