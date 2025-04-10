<?php

class validation {

    public static function validarUsername($username): bool {

        return preg_match('/^[a-zA-Z0-9]{1,20}$/', $username);

    }

    public static function validarPassword($password): bool {

        return (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $nuevaPassword));
    }
}