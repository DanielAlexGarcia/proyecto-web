<?php

class empleado {
    private $IDPersona;
    private $cargo;
    private $especializacion;
    private $salario;

    public function __construct($personaID = null, $cargo = null, $especializacion = null, $salario = null) {
        $this->IDPersona = $personaID;
        $this->cargo = $cargo;
        $this->especializacion = $especializacion;
        $this->salario = $salario;
    }

    // Getters
    public function getIDPersona() {
        return $this->IDPersona;
    }

    public function getCargo() {
        return $this->cargo;
    }

    public function getEspecializacion() {
        return $this->especializacion;
    }

    public function getSalario() {
        return $this->salario;
    }

    // Setters
    public function setIDPersona($IDPersona) {
        $this->IDPersona = $IDPersona;
    }

    public function setCargo($cargo) {
        $this->cargo = $cargo;
    }

    public function setEspecializacion($especializacion) {
        $this->especializacion = $especializacion;
    }

    public function setSalario($salario) {
        $this->salario = $salario;
    }
}

?>