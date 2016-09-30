<?php

/**
 * Pukiwikiプラグイン::動画視聴 受講申し込み
 *
 * PHP version 5.3.10
 * Pukiwiki Version 1.4.7
 *
 * @category MovieViewer
 * @package  Models.DealPack
 * @author   Toshiyuki Ando <couger@kt.rim.or.jp>
 * @license  Apache License 2.0
 * @link     (T.B.D)
 */

//---- (上のコメントをファイルのコメントと認識させるためのコメント)

class MovieViewerTransferDeadline extends DateTime
{
    function __construct($params)
    {
        parent::__construct($params);
    }
}

class MovieViewerDealPackPrice
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

class MovieViewerDealPackFixedPrice extends MovieViewerDealPackPrice
{
}

class MovieViewerDealPackDiscountPrice extends MovieViewerDealPackPrice
{
}

class MovieViewerDiscountPeriod extends MovieViewerPeriod
{
    function __construct($date_begin, $date_end)
    {
        parent::__construct($date_begin, $date_end);
    }

    function canDiscount($date_target = null)
    {
        return $this->isBetween($date_target);
    }
}

class MovieViewerNeverDiscountPeriod
{
    function canDiscount($date_target = null)
    {
        return false;
    }
}

class MovieViewerDealPack
{
    private $_course_id = '';
    private $_pack_number;
    private $_session_ids = array();
    private $_fixed_price;
    private $_discount_price;
    private $_report_form_id;
    private $_course;

    function __construct($course_id, $pack_number, $session_ids, $fixed_price, $discount_price, $report_form_id) {
        $this->_course_id = $course_id;
        $this->_pack_number = $pack_number;
        $this->_session_ids = $session_ids;
        $this->_fixed_price = $fixed_price;
        $this->_discount_price = $discount_price;
        $this->_report_form_id = $report_form_id;

        $courses = plugin_movieviewer_get_courses_repository()->find();
        $this->_course = $courses->getCourse($this->_course_id);
    }

    function getId()
    {
        return "{$this->_course_id}-{$this->_pack_number}";
    }

    function getCourseId()
    {
        return $this->_course->getId();
    }

    function getCourseIdShort()
    {
        return $this->_course->getIdShort();
    }

    function getPackNumber()
    {
        return $this->_pack_number;
    }

    function getCourse()
    {
        return $this->_course;
    }

    function getSessions()
    {
        $objects = array();
        foreach ($this->_session_ids as $session_id) {
            $object = $this->_course->getSession($session_id);
            $objects[] = $object;
        }
        return $objects;
    }

    function getNumSessions()
    {
        return count($this->getSessions());
    }

    function getFixedPrice()
    {
        return $this->_fixed_price;
    }

    function getDiscountPrice()
    {
        return $this->_discount_price;
    }

    function getReportFormId()
    {
        return $this->_report_form_id;
    }

    function describe()
    {
        $first_session = reset($this->getSessions());
        $last_session = end($this->getSessions());
        return "{$this->getCourse()->describe()} {$first_session->describe()}～{$last_session->describe()}";
    }

    function describeShort()
    {
        $first_session = reset($this->getSessions());
        $last_session = end($this->getSessions());
        return "{$this->getCourse()->describeShort()}{$first_session->describeShort()}～{$last_session->describeShort()}";
    }
}

class MovieViewerDealBox
{
    public $course_id = '';
    public $packs = array();

    function __construct($course_id)
    {
        $this->course_id = $course_id;
    }

    function getPackById($id)
    {
        if (array_key_exists($id, $this->packs)) {
            return $this->packs[$id];
        }
        return null;
    }
    
    function getFirstPack()
    {
        reset($this->packs);
        return current($this->packs);
    }
    
    function getNextPack($id)
    {
        reset($this->packs);
        do {
            $current = current($this->packs);
            if ($current->getId() === $id ) {
                $next = next($this->packs);
                if ($next === false) { 
                    $next = null;
                }
                return $next;
            }
        } while (next($this->packs) !== false);
        return null;
    }

    function getLastPack()
    {
        return end($this->packs);
    }

    function addPack($pack_id, $session_ids, $fixed_price, $discount_price, $report_form_id)
    {
        $pack = new MovieViewerDealPack($this->course_id, $pack_id, $session_ids, $fixed_price, $discount_price, $report_form_id);
        $this->packs[$pack->getId()] = $pack;
    }
}

# S4 => Session4つまとめたものって意味
class MovieViewerS4K1KisoDealBox extends MovieViewerDealBox
{
    function __construct()
    {
        parent::__construct("K1Kiso");

        $fixed_price = new MovieViewerDealPackFixedPrice(4750, 4, 1520);
        $discount_price = new MovieViewerDealPackDiscountPrice(4500, 4, 1440);

        $this->addPack(1, array("01", "02", "03", "04"), $fixed_price, $discount_price, "S9041343");
        $this->addPack(2, array("05", "06", "07", "08"), $fixed_price, $discount_price, "S75172099");
        $this->addPack(3, array("09", "10", "11", "12"), $fixed_price, $discount_price, "S70745104");
    }
}

class MovieViewerS4K2KisoDealBox extends MovieViewerDealBox
{
    function __construct()
    {
        parent::__construct("K2Kiso");

        $fixed_price = new MovieViewerDealPackFixedPrice(4750, 4, 1520);
        $discount_price = new MovieViewerDealPackDiscountPrice(4500, 4, 1440);

        $this->addPack(1, array("01", "02", "03", "04"), $fixed_price, $discount_price, "");
        $this->addPack(2, array("05", "06", "07", "08"), $fixed_price, $discount_price, "");
        $this->addPack(3, array("09", "10", "11", "12"), $fixed_price, $discount_price, "");
    }
}

class MovieViewerS4OABunkaDealBox extends MovieViewerDealBox
{
    function __construct()
    {
        parent::__construct("OABunka");

        $fixed_price = new MovieViewerDealPackFixedPrice(4750, 4, 1520);
        $discount_price = new MovieViewerDealPackDiscountPrice(4500, 4, 1440);

        $this->addPack(1, array("01", "02", "03", "04"), $fixed_price, $discount_price, "");
        $this->addPack(2, array("05", "06", "07", "08"), $fixed_price, $discount_price, "");
        $this->addPack(3, array("09", "10", "11", "12"), $fixed_price, $discount_price, "");
    }
}

class MovieViewerS4OBJyoSanDealBox extends MovieViewerDealBox
{
    function __construct()
    {
        parent::__construct("OBJyoSan");

        $fixed_price = new MovieViewerDealPackFixedPrice(4750, 4, 1520);
        $discount_price = new MovieViewerDealPackDiscountPrice(4500, 4, 1440);

        $this->addPack(1, array("01", "02", "03", "04"), $fixed_price, $discount_price, "");
        $this->addPack(2, array("05", "06", "07", "08"), $fixed_price, $discount_price, "");
        $this->addPack(3, array("09", "10", "11", "12"), $fixed_price, $discount_price, "");
    }
}

class MovieViewerS4DealContainer
{
    public $boxes = array();

    function __construct()
    {
        $this->addBox(new MovieViewerS4K1KisoDealBox());
        $this->addBox(new MovieViewerS4K2KisoDealBox());
        $this->addBox(new MovieViewerS4OABunkaDealBox());
        $this->addBox(new MovieViewerS4OBJyoSanDealBox());
    }

    function getBox($course_id)
    {
        return $this->boxes[$course_id];
    }

    function getPack($pack_id)
    {
        foreach ($this->boxes as $box) {
            $pack = $box->getPackById($pack_id);
            if ($pack !== null) {
                return $pack;
            }
        }
        return null;
    }

    function addBox($box)
    {
        $this->boxes[$box->course_id] = $box;
    }
}

?>