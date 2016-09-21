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
    public $course_id;
    public $session_id;

    function __construct($course_and_session_id) {
        $params = split("_", $course_and_session_id);
        if (count($params) !== 2) {
            throw new InvalidArgumentException();
        }
        $this->course_id = $params[0];
        $this->session_id = $params[1];
    }
}

class MovieViewerReviewPack {
    private $items;
    private $price;

    function __construct($course_and_session_ids) {
        $this->items = array();

        $params = split(",", $course_and_session_ids);
        foreach($params as $param) {
            if ($param !== '') {
                $this->items[] = new MovieViewerReviewPackItem($param);
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
}

?>