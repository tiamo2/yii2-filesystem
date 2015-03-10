<?php

use yii\db\Schema;
use yii\db\Migration;

class m150305_173636_filesystem extends Migration
{
    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }
		
        $this->createTable('{{%file}}', [
            'id' => Schema::TYPE_PK,
            'storage' => Schema::TYPE_STRING . '(10)',
            'path' => Schema::TYPE_STRING . ' NOT NULL',
            'extension' => Schema::TYPE_STRING . '(10) NOT NULL',
            'format' => Schema::TYPE_STRING,
            'type' => Schema::TYPE_STRING . ' NOT NULL',
            'size' => Schema::TYPE_INTEGER . ' NOT NULL',
            'hash' => Schema::TYPE_STRING . '(32) NOT NULL',
            'owner_model' => Schema::TYPE_STRING,
            'owner_id' => Schema::TYPE_INTEGER,
            'expired' => Schema::TYPE_DATETIME,
            'created' => Schema::TYPE_DATETIME,
        ], $tableOptions);
		
		$this->createIndex('file_hash', '{{%file}}', 'hash');
		$this->createIndex('file_owner', '{{%file}}', ['owner_model', 'owner_id']);
    }

    public function down()
    {
		$this->dropTable('{{%file}}');
    }
}