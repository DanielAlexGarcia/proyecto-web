<?php
require_once __DIR__ . "/persona.php";
require_once __DIR__ . "/paciente.php";

class NewUsersPac
{
    private persona $persona;
    private paciente $paciente;

    public function __construct(?persona $persona = null, ?paciente $paciente = null)
    {
        $this->persona  = $persona  ?? new persona();
        $this->paciente = $paciente ?? new paciente();
    }

    public function getPersona(): persona
    {
        return $this->persona;
    }

    public function getPaciente(): paciente
    {
        return $this->paciente;
    }

    public function setPersona(persona $persona): void
    {
        $this->persona = $persona;
    }

    public function setPaciente(paciente $paciente): void
    {
        $this->paciente = $paciente;
    }
}