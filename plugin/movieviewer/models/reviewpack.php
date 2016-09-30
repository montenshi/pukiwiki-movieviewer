<?php

/**
 * Pukiwikiプラグイン::動画視聴 再視聴
 *
 * PHP version 5.3.10
 * Pukiwiki Version 1.4.7
 *
 * @category MovieViewer
 * @package  Models.Review
 * @author   Toshiyuki Ando <couger@kt.rim.or.jp>
 * @license  Apache License 2.0
 * @link     (T.B.D)
 */

//---- (上のコメントをファイルのコメントと認識させるためのコメント)

class MovieViewerReviewPackPrice
{
    public $unit_amount_without_tax;
    public $num_units;
    public $tax_amount;

    function __construct($unit_amount_without_tax, $num_units, $tax_amount)
    {
        $this->unit_amount_without_tax = $unit_amount_without_tax;
        $this->num_units = $num_units;
        $this->tax_amount = $tax_amount;
    }

    function getTotalAmountWithoutTax()
    {
        return $this->unit_amount_without_tax * $this->num_units;
    }

    function getTotalAmountWithTax()
    {
        return $this->getTotalAmountWithoutTax() + $this->tax_amount;
    }
}

class MovieViewerReviewPackItem
{
    static function createInstanceFromId($id)
    {
        $params = split("_", $id);
        if (count($params) !== 2) {
            throw new InvalidArgumentException();
        }
        $object = new MovieViewerReviewPackItem($params[0], $params[1]);
        return $object;
    }

    public $course_id;
    public $session_id;

    private $_course;

    function __construct($course_id, $session_id)
    {
        $this->course_id = $course_id;
        $this->session_id = $session_id;

        $courses = plugin_movieviewer_get_courses_repository()->find();
        $this->_course = $courses->getCourse($this->course_id);
    }

    function getId()
    {
        return "{$this->course_id}_{$this->session_id}";
    }

    function getCourse()
    {
        return $this->_course;
    }

    function getSession()
    {
        return $this->getCourse()->getSession($this->session_id);
    }

    function describe()
    {
        return "{$this->getCourse()->describe()} {$this->getSession()->describe()}";
    }
}

class MovieViewerReviewPack
{
    const UNIT_AMOUNT_WITH_OUT_TAX = 500;
    const TAX_PER_AMOUNT = 40;

    private $_items;
    private $_price;

    function __construct($item_ids)
    {
        $this->_items = array();

        foreach ($item_ids as $item_id) {
            if ($item_id !== '') {
                $this->_items[] = MovieViewerReviewPackItem::createInstanceFromId($item_id);
            }
        }

        $num_units = count($this->_items);
        $unit_amount_without_tax = self::UNIT_AMOUNT_WITH_OUT_TAX;
        $tax_amount = self::TAX_PER_AMOUNT * $num_units;
        $this->_price = new MovieViewerReviewPackPrice($unit_amount_without_tax, $num_units, $tax_amount);
    }

    function getItems()
    {
        return $this->_items;
    }

    function getPrice()
    {
        return $this->_price;
    }

    function getItemsByCourse()
    {
        $sorted = array();
        $current_course_id = '';
        foreach ($this->_items as $item) {
            if ($current_course_id !== $item->course_id) {
                $sorted[$item->course_id] = array();
                $current_course_id = $item->course_id;
            }
            $sorted[$item->course_id][] = $item;
        }
        return $sorted;
    }

    function hasItem($course_id, $session_id)
    {
        foreach ($this->getItems() as $item) {
            if ($item->course_id === $course_id && $item->session_id === $session_id) {
                return true;
            }
        }
        return false;
    }

    function describe()
    {
        $item_count = count($this->_items);
        if ($item_count === 0) {
            return "(なし)";
        }

        $first_item = $this->_items[0];

        $description = "再視聴 {$first_item->describe()}";

        if ($item_count > 1) {
            $others_count = $item_count - 1;
            $description = "{$description} 他{$others_count}回";
        }

        return $description;
    }

    function describeShort()
    {
        $item_count = count($this->_items);
        if ($item_count === 0) {
            return "(なし)";
        }

        return "再視聴 {$item_count}回";
    }
}

?>