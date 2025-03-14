<?php

// create_validate_transaction_balance_trigger.php
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class CreateValidateTransactionBalanceTrigger extends Migration
{
    public function up()
    {
        DB::unprepared('
            CREATE TRIGGER validate_transaction_balance
            BEFORE INSERT ON transactions
            FOR EACH ROW
            BEGIN
                DECLARE user_balance INT;
                
                SELECT balance INTO user_balance
                FROM users 
                WHERE id = NEW.user_id;
                
                IF user_balance < NEW.price THEN
                    SIGNAL SQLSTATE "45000" 
                    SET MESSAGE_TEXT = "Insufficient balance";
                END IF;
            END
        ');
    }

    public function down()
    {
        DB::unprepared('DROP TRIGGER IF EXISTS validate_transaction_balance');
    }
}
