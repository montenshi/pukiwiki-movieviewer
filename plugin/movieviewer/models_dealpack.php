<?php

class MovieViewerTransferDeadline extends DateTime {

    function __construct($params) {
        parent::__construct($params);
    }
}

class MovieViewerFixedPrice {
    public $amount;

    function __construct($amount) {
        $this->amount = $amount;
    }
}

class MovieViewerDiscountPrice {
    public $amount;

    function __construct($amount) {
        $this->amount = $amount;
    }
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
    private $pack_id;
    private $session_ids = array();
    private $fixed_price;
    private $discount_price;
    private $course;

    function __construct($course_id, $pack_id, $session_ids, $fixed_price, $discount_price) {
        $this->course_id = $course_id;
        $this->pack_id = $pack_id;
        $this->session_ids = $session_ids;
        $this->fixed_price = new MovieViewerFixedPrice($fixed_price);
        $this->discount_price = new MovieViewerDiscountPrice($discount_price);

        $courses = plugin_movieviewer_get_courses_repository()->find();
        $this->course = $courses->getCourse($this->course_id);
    }

    public function getId() {
        return "{$this->course_id}-{$this->pack_id}";
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

    public function getFixedPrice() {
        return $this->fixed_price;
    }

    public function getDiscountPrice() {
        return $this->discount_price;
    }

    public function describe() {
        $first_session = reset($this->getSessions());
        $last_session = end($this->getSessions());
        return "コース {$this->getCourse()->name} {$first_session->name}～{$last_session->name}";
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

    function addPack($pack_id, $session_ids, $fixed_price, $discount_price) {
        $pack = new MovieViewerDealPack($this->course_id, $pack_id, $session_ids, $fixed_price, $discount_price);
        $this->packs[$pack->getId()] = $pack;
    }
}

class MovieViewerS4K1KisoDealBox extends MovieViewerDealBox {
    function __construct() {
        parent::__construct("K1Kiso");
        $this->addPack(1, array("01", "02", "03", "04"), 20520, 19440);
        $this->addPack(2, array("05", "06", "07", "08"), 20520, 19440);
        $this->addPack(3, array("09", "10", "11", "12"), 20520, 19440);
    }
}

class MovieViewerS4K2KisoDealBox extends MovieViewerDealBox {
    function __construct() {
        parent::__construct("K2Kiso");
        $this->addPack(1, array("01", "02", "03", "04"), 20520, 19440);
        $this->addPack(2, array("05", "06", "07", "08"), 20520, 19440);
        $this->addPack(3, array("09", "10", "11", "12"), 20520, 19440);
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