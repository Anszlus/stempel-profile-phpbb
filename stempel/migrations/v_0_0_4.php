<?php

namespace anszlus\stempel\migrations;

class v_0_0_4 extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        // Sprawdza, czy nowa konfiguracja jest już zainstalowana
        return isset($this->config['anszlus_stempel_users_updated_at']);
    }

    static public function depends_on()
    {
        return [
            '\anszlus\stempel\migrations\v_0_0_1',  // Zależy od poprzedniej migracji
        ];
    }

    public function update_schema()
    {
        // Nie dodajemy żadnych zmian w schemacie, więc zwracamy pustą tablicę
        return [];
    }

    public function revert_schema()
    {
        // Nie ma zmian do cofnięcia w schemacie, więc zwracamy pustą tablicę
        return [];
    }

    public function update_data()
    {
        return [
            // Dodajemy nowy wpis do tabeli config
            ['config.add', ['anszlus_stempel_users_updated_at', 0]],
        ];
    }
}
