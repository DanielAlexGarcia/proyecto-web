<?php

class paciente {
    private $IDPersona;
    private $numSeg;
    private $grupSangineo;
    private $alergias;

    public function __construct($personaID = null, $numSeguro = null, $gsangineo = null, $alergia = null) {
        $this->IDPersona = $personaID;
        $this->numSeg = $numSeguro;
        $this->grupSangineo = $gsangineo;
        $this->alergias = $alergia;
    }

    // Getters
    public function getIDPersona() {
        return $this->IDPersona;
    }

    public function getNumSeg() {
        return $this->numSeg;
    }

    public function getGrupSangineo() {
        return $this->grupSangineo;
    }

    public function getAlergias() {
        return $this->alergias;
    }

    // Setters
    public function setIDPersona($IDPersona) {
        $this->IDPersona = $IDPersona;
    }

    public function setNumSeg($numSeg) {
        $this->numSeg = $numSeg;
    }

    public function setGrupSangineo($grupSangineo) {
        $this->grupSangineo = $grupSangineo;
    }

    public function setAlergias($alergias) {
        $this->alergias = $alergias;
    }
}

?>