<?php

class validation {

    //el formato del username solo puede contener letras y numeros y no puede tener espacios
    public static function validarUsername($username): bool {

        return (preg_match('/^[a-zA-Z0-9]{1,20}$/', $username));

    }

    //el formato del nombre solo puede contener letras y no puede tener espacios
    public static function validarNombre($nombre): bool {

        return (preg_match('/^[a-zA-Z]{3,20}$/', $nombre));
    }

    //el formato de la contraseña debe tener al menos 8 caracteres, al menos una letra mayúscula, al menos una letra minúscula,
    //al menos un número y al menos un caracter especial. No puede tener espacios
    public static function validarPassword($password): bool {

        return (preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password));
    }


}