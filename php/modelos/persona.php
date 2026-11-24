<?php

class persona {
    private $nombres;
    private $primerApellido;
    private $segundoApellido;
    private $fechaNacimiento;
    private $genero;
    private $Direccion;

    public function __construct($nombres = null, $apellido1 = null, $apellido2 = null, $fechaNaci = null, $genero = null, $direccion = null) {
        $this->nombres = $nombres;
        $this->primerApellido = $apellido1;
        $this->segundoApellido = $apellido2;
        $this->fechaNacimiento = $fechaNaci;
        $this->genero = $genero;
        $this->Direccion = $direccion;
    }

    // Getters
    public function getNombres() {
        return $this->nombres;
    }

    public function getPrimerApellido() {
        return $this->primerApellido;
    }

    public function getSegundoApellido() {
        return $this->segundoApellido;
    }

    public function getFechaNacimiento() {
        return $this->fechaNacimiento;
    }

    public function getGenero() {
        return $this->genero;
    }

    public function getCURP() {
        return $this->Direccion;
    }

    // Setters
    public function setNombres($nombres) {
        $this->nombres = $nombres;
    }

    public function setPrimerApellido($primerApellido) {
        $this->primerApellido = $primerApellido;
    }

    public function setSegundoApellido($segundoApellido) {
        $this->segundoApellido = $segundoApellido;
    }

    public function setFechaNacimiento($fechaNacimiento) {
        $this->fechaNacimiento = $fechaNacimiento;
    }

    public function setGenero($genero) {
        $this->genero = $genero;
    }

    public function setCURP($direccion) {
        $this->Direccion = $direccion;
    }
}
?>