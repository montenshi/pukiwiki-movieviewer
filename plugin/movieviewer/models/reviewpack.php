<?php

class MovieViewerReviewPackPrice {
    public $unit_amount_without_tax;
    public $num_units;
    public $tax_amount;

    function __construct($unit_amount_without_tax, $num_units, $tax_amount) {
        $this->unit_amount_without_tax = $unit_amount_without_tax;
        $this->num_units = $num_units;
        $this->tax_amount = $tax_amount;
    }

    function getTotalAmountWithoutTax() {
        return $this->unit_amount_without_tax * $this->num_units;
    }

    function getTotalAmountWithTax() {
        return $this->getTotalAmountWithoutTax() + $this->tax_amount;
    }
}

class MovieViewerReviewPackItem {

    static function createInstanceFromId($id) {
        $params = split("_", $id);
        if (count($params) !== 2) {
            throw new InvalidArgumentException();
        }
        $object = new MovieViewerReviewPackItem($params[0], $params[1]);
        return $object;
    }

    public $course_id;
    public $session_id;

    private $course;

    function __construct($course_id, $session_id) {
        $this->course_id = $course_id;
        $this->session_id = $session_id;

        $courses = plugin_movieviewer_get_courses_repository()->find();
        $this->course = $courses->getCourse($this->course_id);
    }

    function getId() {
        return "{$this->course_id}_{$this->session_id}";
    }

    function getCourse() {
        return $this->course;
    }

    function getSession() {
        return $this->getCourse()->getSession($this->session_id);
    }

    function describe() {
        return "{$this->getCourse()->describe()} {$this->getSession()->describe()}";
    }
}

class MovieViewerReviewPack {
    private $items;
    private $price;

    function __construct($item_ids) {
        $this->items = array();

        foreach($item_ids as $item_id) {
            if ($item_id !== '') {
                $this->items[] = MovieViewerReviewPackItem::createInstanceFromId($item_id);
            }
        }

        $num_units = count($this->items);
        $unit_amount_without_tax = 500;
        $tax_amount = 40 * $num_units;
        $this->price = new MovieViewerReviewPackPrice($unit_amount_without_tax, $num_units, $tax_amount);
    }

    function getItems() {
        return $this->items;
    }

    function getPrice() {
        return $this->price;
    }

    function getItemsByCourse() {
        $sorted = array();
        $current_course_id = '';
        foreach ($this->items as $item) {
            if ($current_course_id !== $item->course_id) {
                $sorted[$item->course_id] = array();
                $current_course_id = $item->course_id;
            }
            $sorted[$item->course_id][] = $item;
        }
        return $sorted;
    }

    function describe() {
        $item_count = count($this->items);
        if ($item_count === 0) {
            return "(なし)";
        }

        $first_item = $this->items[0];

        $description = "再視聴 {$first_item->describe()}";

        if ($item_count > 1) {
            $others_count = $item_count - 1;
            $description = "{$description} 他{$others_count}個";
        }

        return $description;
    }

    function describeShort() {
        $item_count = count($this->items);
        if ($item_count === 0) {
            return "(なし)";
        }

        return "再視聴 {$item_count}個";
    }
}

?>