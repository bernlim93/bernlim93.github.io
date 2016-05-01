<?php
class ChatUser extends ChatBase{

    protected $name = '', $email = '';

    public function save(){

        DB::query("
            INSERT INTO Users(name, email)
            VALUES (
                '".DB::esc($this->name)."',
                '".DB::esc($this->email)."'
        )");

        return DB::getMySQLiObject();
    }

    public function update(){
        DB::query("
            INSERT INTO Users(name, email)
            VALUES (
                '".DB::esc($this->name)."',
                '".DB::esc($this->email)."'
            ) ON DUPLICATE KEY UPDATE last_activity = NOW()");
    }
}
?>