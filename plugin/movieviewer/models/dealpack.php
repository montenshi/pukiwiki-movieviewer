<?php

class MovieViewerTransferDeadline extends DateTime {

    function __construct($params) {
        parent::__construct($params);
    }
}

class MovieViewerDealPackPrice {
    public $unit_amount_without_tax;
    public $num_units;
    public $tax_amount;

    function __construct($unit_amount_without_tax, $num_units, $tax_amount) {
        $this->unit_amount_without_tax = $unit_amount_without_tax;
        $this->num_units = $num_units;
        $this->tax_amount = $tax_amount;
    }

    public function getTotalAmountWithoutTax() {
        return $this->unit_amount_without_tax * $this->num_units;
    }

    public function getTotalAmountWithTax() {
        return $this->getTotalAmountWithoutTax() + $this->tax_amount;
    }
}

class MovieViewerDealPackFixedPrice extends MovieViewerDealPackPrice {
}

class MovieViewerDealPackDiscountPrice extends MovieViewerDealPackPrice {
}

class MovieViewerDiscountPeriod extends MovieViewerPeriod {
    function __construct($date_begin, $date_end) {
        parent::__construct($date_begin, $date_end);
    }

    public function canDiscount($date_target = null) {
        return $this->isBetween($date_target);
    }
}

class MovieViewerNeverDiscountPeriod {
    public function canDiscount($date_target = null) {
        return FALSE;
    }
}

class MovieViewerDealPack {
    private $course_id = '';
    private $pack_number;
    private $session_ids = array();
    private $fixed_price;
    private $discount_price;
    private $report_form_id;
    private $course;

    function __construct($course_id, $pack_number, $session_ids, $fixed_price, $discount_price, $report_form_id) {
        $this->course_id = $course_id;
        $this->pack_number = $pack_number;
        $this->session_ids = $session_ids;
        $this->fixed_price = $fixed_price;
        $this->discount_price = $discount_price;
        $this->report_form_id = $report_form_id;

        $courses = plugin_movieviewer_get_courses_repository()->find();
        $this->course = $courses->getCourse($this->course_id);
    }

    public function getId() {
        return "{$this->course_id}-{$this->pack_number}";
    }

    public function getCourseId() {
        return $this->course->getId();
    }

    public function getCourseIdShort() {
        return $this->course->getIdShort();
    }

    public function getPackNumber() {
        return $this->pack_number;
    }

    public function getCourse() {
        return $this->course;
    }

    public function getSessions() {
        $objects = array();
        foreach($this->session_ids as $session_id) {
            $object = $this->course->getSession($session_id);
            $objects[] = $object;
        }
        return $objects;
    }

    public function getNumSessions() {
        return count($this->getSessions());
    }

    public function getFixedPrice() {
        return $this->fixed_price;
    }

    public function getDiscountPrice() {
        return $this->discount_price;
    }

    public function getReportFormId() {
        return $this->report_form_id;
    }

    public function describe() {
        $first_session = reset($this->getSessions());
        $last_session = end($this->getSessions());
        return "{$this->getCourse()->describe()} {$first_session->describe()}～{$last_session->describe()}";
    }

    public function describeShort() {
        $first_session = reset($this->getSessions());
        $last_session = end($this->getSessions());
        return "{$this->getCourse()->describeShort()}{$first_session->describeShort()}～{$last_session->describeShort()}";
    }
}

class MovieViewerDealBox {
    public $course_id = '';
    public $packs = array();

    function __construct($course_id) {
        $this->course_id = $course_id;
    }

    public function getPackById($id) {
        return $this->packs[$id];
    }
    
    public function getNextPack($id) {
        reset($this->packs);
        do {
            $current = current($this->packs);
            if ($current->getId() === $id ) {
                $next = next($this->packs);
                if ($next === FALSE) { $next = NULL; }
                return $next;
            }
        } while (next($this->packs) !== FALSE);
        return NULL;
    }

    function addPack($pack_id, $session_ids, $fixed_price, $discount_price, $report_form_id) {
        $pack = new MovieViewerDealPack($this->course_id, $pack_id, $session_ids, $fixed_price, $discount_price, $report_form_id);
        $this->packs[$pack->getId()] = $pack;
    }
}

# S4 => Session4つまとめたものって意味
class MovieViewerS4K1KisoDealBox extends MovieViewerDealBox {
    function __construct() {
        parent::__construct("K1Kiso");

        $fixed_price = new MovieViewerDealPackFixedPrice(4750, 4, 1520);
        $discount_price = new MovieViewerDealPackDiscountPrice(4500, 4, 1440);

        $this->addPack(1, array("01", "02", "03", "04"), $fixed_price, $discount_price, "S9041343");
        $this->addPack(2, array("05", "06", "07", "08"), $fixed_price, $discount_price, "S75172099");
        $this->addPack(3, array("09", "10", "11", "12"), $fixed_price, $discount_price, "");
    }
}

class MovieViewerS4K2KisoDealBox extends MovieViewerDealBox {
    function __construct() {
        parent::__construct("K2Kiso");

        $fixed_price = new MovieViewerDealPackFixedPrice(4750, 4, 1520);
        $discount_price = new MovieViewerDealPackDiscountPrice(4500, 4, 1440);

        $this->addPack(1, array("01", "02", "03", "04"), $fixed_price, $discount_price);
        $this->addPack(2, array("05", "06", "07", "08"), $fixed_price, $discount_price);
        $this->addPack(3, array("09", "10", "11", "12"), $fixed_price, $discount_price);
    }
}

class MovieViewerS4DealContainer {
    public $boxes = array();

    function __construct() {
        $this->addBox(new MovieViewerS4K1KisoDealBox());
        $this->addBox(new MovieViewerS4K2KisoDealBox());
    }

    public function getBox($course_id) {
        return $this->boxes[$course_id];
    }

    public function getPack($pack_id) {
        foreach ($this->boxes as $box) {
            $pack = $box->getPackById($pack_id);
            if ($pack !== NULL) {
                return $pack;
            }
        }
        return NULL;
    }

    public function addBox($box) {
        $this->boxes[$box->course_id] = $box;
    }
}

?>