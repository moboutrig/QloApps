<?php
class Tax extends TaxCore
{
    const TAX_TYPE_PERCENT = 1;
    const TAX_TYPE_PER_PERSON_PER_NIGHT = 2;

    public $type;
    public $amount;

    public function __construct($id = null, $id_lang = null, $id_shop = null)
    {
        self::$definition['fields']['type'] = array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId');
        self::$definition['fields']['amount'] = array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice');
        parent::__construct($id, $id_lang, $id_shop);
    }
}
