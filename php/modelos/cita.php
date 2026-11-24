<?php

class Cita {
    private $fechaHora;
    private $motivo;
    private $estado;
    private $pacienteID;
    private $doctorID;

    public function __construct($fechaHora = null, $motivo = null, $estado = null, $pacienteID = null, $doctorID = null) {
        $this->fechaHora = $fechaHora;
        $this->motivo = $motivo;
        $this->estado = $estado;
        $this->pacienteID = $pacienteID;
        $this->doctorID = $doctorID;
    }

    // Getters
    public function getFechaHora() {
        return $this->fechaHora;
    }

    public function getMotivo() {
        return $this->motivo;
    }

    public function getEstado() {
        return $this->estado;
    }

    public function getPacienteID() {
        return $this->pacienteID;
    }

    public function getDoctorID() {
        return $this->doctorID;
    }

    // Setters
    public function setFechaHora($fechaHora) {
        $this->fechaHora = $fechaHora;
    }

    public function setMotivo($motivo) {
        $this->motivo = $motivo;
    }

    public function setEstado($estado) {
        $this->estado = $estado;
    }

    public function setPacienteID($pacienteID) {
        $this->pacienteID = $pacienteID;
    }

    public function setDoctorID($doctorID) {
        $this->doctorID = $doctorID;
    }
}

?>