<?php
/* Chat line is used for the chat entries */

class ChatLine extends ChatBase{

    protected $text = '', $user_id= '', $room_id=0, $sentiment=0.0;

    public function save(){
        DB::query("
            INSERT INTO Messages (user_id, text, room_id, sentiment)
            VALUES (
                ".DB::esc($this->user_id).",
                '".DB::esc($this->text)."',
                ".DB::esc($this->room_id).",
		".DB::esc($this->sentiment)."
        )");

        // Returns the MySQLi object of the DB class

        return DB::getMySQLiObject();
    }
}
?>