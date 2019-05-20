<?php
namespace Kotazuck\Util;

class Jpdt extends \DateTime
{
    /** 祝日一覧 */
    // 種別：
    //   fixed=日付固定
    //   happy=指定の週の月曜日
    //   spring=春分の日専用
    //   autumn=秋分の日専用
    private static $holidays = [
        // 種別, 月, 日or週, 開始年, 終了年, 祝日名
        ['fixed',   1,  1, 1949, 9999, '元日'],
        ['fixed',   1, 15, 1949, 1999, '成人の日'],
        ['happy',   1,  2, 2000, 9999, '成人の日'],
        ['fixed',   2, 11, 1967, 9999, '建国記念の日'],
        ['spring',  3,  0, 1949, 9999, '春分の日'],
        ['fixed',   4, 29, 1949, 1989, '天皇誕生日'],
        ['fixed',   4, 29, 1990, 2006, 'みどりの日'],
        ['fixed',   4, 29, 2007, 9999, '昭和の日'],
        ['fixed',   5,  3, 1949, 9999, '憲法記念日'],
        ['fixed',   5,  4, 1988, 2006, '国民の休日'],
        ['fixed',   5,  4, 2007, 9999, 'みどりの日'],
        ['fixed',   5,  5, 1949, 9999, 'こどもの日'],
        ['happy',   7,  3, 2003, 9999, '海の日'],
        ['fixed',   7, 20, 1996, 2002, '海の日'],
        ['fixed',   8, 11, 2016, 9999, '山の日'],
        ['autumn',  9,  0, 1948, 9999, '秋分の日'],
        ['fixed',   9, 15, 1966, 2002, '敬老の日'],
        ['happy',   9,  3, 2003, 9999, '敬老の日'],
        ['fixed',  10, 10, 1966, 1999, '体育の日'],
        ['happy',  10,  2, 2000, 9999, '体育の日'],
        ['fixed',  11,  3, 1948, 9999, '文化の日'],
        ['fixed',  11, 23, 1948, 9999, '勤労感謝の日'],
        ['fixed',  12, 23, 1989, 9999, '天皇誕生日'],
        //以下、1年だけの祝日
        ['fixed',   4, 10, 1959, 1959, '皇太子明仁親王の結婚の儀'],
        ['fixed',   2, 24, 1989, 1989, '昭和天皇の大喪の礼'],
        ['fixed',  11, 12, 1990, 1990, '即位礼正殿の儀'],
        ['fixed',   6,  9, 1993, 1993, '皇太子徳仁親王の結婚の儀'],
        ['fixed',   5,  1, 2019, 2019, '令和天皇即位'],
    ];

    /**
     * 祝日を取得
     */
    public function holiday()
    {
        // 設定された休日チェック
        $result = $this->checkHoliday();
        if ($result !== false) {
            return $result;
        }

        // 振替休日チェック
        $result = $this->checkTransferHoliday();
        if ($result !== false) {
            return $result;
        }

        // 国民の休日チェック
        $result = $this->checkNationalHoliday();

        return $result;
    }

    /**
     * 設定された休日のみチェック
     * 国民の休日と振替休日はチェックしない
     */
    public function checkHoliday()
    {
        $result = false;

        // 全ての祝日を判定
        foreach (self::$holidays as $holiday) {
            list($method, $month, $day, $start, $end, $name) = $holiday;
            $method .= 'Holiday';
            $result = $this->$method($month, $day, $start, $end, $name);
            if ($result) {
                return $result;
            }
        }
        return $result;
    }

    /**
     * 振替休日チェック
     */
    public function checkTransferHoliday()
    {
        // 施行日チェック
        $d = new static('1973-04-12');
        if ($this < $d) {
            return false;
        }

        // 当日が祝日の場合はfalse
        if ($this->checkHoliday()) {
            return false;
        }

        $num = ($this->year <= 2006) ? 1 : 7; //改正法なら最大7日間遡る

        $d = clone $this;
        $d->modify('-1 day');
        $isTransfer = false;
        for ($i = 0; $i < $num; $i++) {
            if ($d->checkHoliday()) {
                // 祝日かつ日曜ならば振替休日
                if ($d->dayOfWeek == 0) {
                    $isTransfer = true;
                    break;
                }
                $d->modify('-1 day');
            } else {
                break;
            }
        }
        return $isTransfer ? '振替休日' : false;
    }

    /**
     * 国民の休日かどうかチェック
     */
    public function checkNationalHoliday()
    {
        // 施行日チェック
        $d = new static('2003-01-01');
        if ($this < $d) {
            return false;
        }

        $before = clone $this;
        $before->modify('-1 day');
        if ($before->checkHoliday() === false) {
            return false;
        }

        $after = clone $this;
        $after->modify('+1 day');
        if ($after->checkHoliday() === false) {
            return false;
        }

        return '国民の休日';
    }

    /**
     * 固定祝日かどうか
     */
    private function fixedHoliday($month, $day, $start, $end, $name)
    {
        if ($this->isWithinYear($start, $end) === false) {
            return false;
        }
        if ($this->month != $month) {
            return false;
        }

        if ($this->day != $day) {
            return false;
        }
        return $name;
    }

    /**
     * ハッピーマンデー
     */
    private function happyHoliday(
        $month,
        $week,
        $start,
        $end,
        $name
    ) {
        if ($this->isWithinYear($start, $end) === false) {
            return false;
        }
        if ($this->month != $month) {
            return false;
        }

        // 第*月曜日の日付を求める
        $w = 1; // 月曜日固定
        $d1 = new static($this->format('Y-m-1'));
        $w1 = intval($d1->dayOfWeek);
        $day  = $w - $w1 < 0 ? 7 + $w - $w1 : $w - $w1;
        $day++;
        $day = $day + 7 * ($week - 1);

        if ($this->day != $day) {
            return false;
        }
        return $name;
    }

    /**
     * 春分の日
     */
    private function springHoliday($month, $dummy, $start, $end, $name)
    {
        if ($this->isWithinYear($start, $end) === false) {
            return false;
        }
        if ($this->month != $month) {
            return false;
        }

        $year = $this->year;
        $day = floor(20.8431 + 0.242194 * ($year - 1980) - floor(($year - 1980) / 4));

        if ($this->day != $day) {
            return false;
        }
        return $name;
    }

    /**
     * 秋分の日
     */
    private function autumnHoliday($month, $dummy, $start, $end, $name)
    {
        if ($this->isWithinYear($start, $end) === false) {
            return false;
        }
        if ($this->month != $month) {
            return false;
        }

        $year = $this->year;
        $day = floor(23.2488 + 0.242194 * ($year - 1980) - floor(($year - 1980) / 4));

        if ($this->day != $day) {
            return false;
        }
        return $name;
    }

    /**
     * 年が祝日適用範囲内であるか
     */
    private function isWithinYear($start, $end)
    {
        if ($this->year < $start || $end < $this->year) {
            return false;
        }
        return true;
    }

    public static function from(
        $Y = 2000,
        $m = 1,
        $d = 1,
        $H = 0,
        $i = 0,
        $s = 0
    ) {
        return new static($Y . "-" . str_pad($m, 2, "0", STR_PAD_LEFT) . "-" . str_pad($d, 2, "0", STR_PAD_LEFT) . " " . str_pad($H, 2, "0", STR_PAD_LEFT) . ":" . str_pad($H, 2, "0", STR_PAD_LEFT) . ":" . str_pad($H, 2, "0", STR_PAD_LEFT));
    }

    // 各種パラメータ
    public function getYear()
    {
        return intval($this->format("Y"));
    }
    public function getMonth()
    {
        return intval($this->format("n"));
    }
    public function getDay()
    {
        return intval($this->format("j"));
    }
    public function getHour()
    {
        return intval($this->format("G"));
    }
    public function getMinute()
    {
        return intval($this->format("i"));
    }
    public function getSecond()
    {
        return intval($this->format("s"));
    }
    public function getWeek()
    {
        return intval($this->format("w"));
    }
    public function getWeekJa()
    {
        switch ($this->getWeek()) {
            case 0:
                return "日";
            case 1:
                return "月";
            case 2:
                return "火";
            case 3:
                return "水";
            case 4:
                return "木";
            case 5:
                return "金";
            case 6:
                return "土";
        }
    }

    // Y-m-dの形で出力
    public function getDate()
    {
        return $this->format("Y-m-d");
    }

    // 日のセット
    public function setDay($day)
    {
        $y = $this->getYear();
        $m = $this->getMonth();

        // 当月末
        $lastDayOfMonth = $this->getLastDayOfMonth()->getDay();

        $d = ($day < $lastDayOfMonth) ? $day : $lastDayOfMonth;

        $this->modify($y . "-" . str_pad($m, 2, "0", STR_PAD_LEFT) . "-" . str_pad($d, 2, "0", STR_PAD_LEFT));
        return $this;
    }

    // 月末
    public function getLastDayOfMonth()
    {
        $this->modify("last day of this month");
        return $this;
    }
    // 月初
    public function getFirstDayOfMonth()
    {
        $this->modify("first day of this month");
        return $this;
    }


    // 直近の引数の日
    public function recentDay($day = 1)
    {
        $y = $this->getYear();
        $m = $this->getMonth();
        $d = $this->getDay();

        if ($d >= $day) { // 今月
            $this->setDay($day);
        } else { // 先月
            $this->modify("first day of last month")->setDay($day);
        }
        return $this;
    }

    public function recentMonth($month = 1)
    {
        $y = $this->getYear();
        $m = $this->getMonth();

        if ($m >= $month) { // 今月
            $this->setMonth($month);
        } else { // 先月
            $this->modify("-1 years")->setMonth($month);
        }
        return $this;
    }

    // 直後の引数の日
    public function followDay($day = 31)
    {
        $y = $this->getYear();
        $m = $this->getMonth();
        $d = $this->getDay();

        if ($d <= $day) {
            $this->setDay($day);
        } else {
            $this->modify("first day of next month")->setDay($day);
        }
        return $this;
    }
    public function followMonth($month = 12)
    {
        $y = $this->getYear();
        $m = $this->getMonth();
        $d = $this->getDay();

        if ($m <= $month) { //
            $this->setMonth($month);
        } else { //
            $this->modify("+1 years")->setMonth($month);
        }
        return $this;
    }

    // 直近の曜日
    public function recentWeek($week = 0)
    {
        $w = $this->getWeek();
        $diff = $w >= $week ? $w - $week : $w - $week + 7;
        return $this->modify("-{$diff} days");
    }

    // 直後の曜日
    public function followWeek($week = 6)
    {
        $w = $this->getWeek();
        $diff = $w <= $week ? $week - $w : $week - $w + 7;
        return $this->modify("+{$diff} days");
    }



    public function addMonth($add)
    {
        $_y = $this->getYear();
        $_m = $this->getMonth();
        $_d = $this->getDay();

        $lastDay = (clone $this)->getLastDayOfMonth()->getDay();
        $add = intval($add);
        $addMonth = $add % 12;
        $addYear = intval($add / 12);

        $y = $_y + $addYear;
        $m = ($_m + $addMonth);
        if (($_m + $addMonth) > 12) {
            $m = ($_m + $addMonth) - 12;
        }
        if (($_m + $addMonth) < 1) {
            $m = ($_m + $addMonth) + 12;
        }

        if (($_m + $addMonth) > 12) {
            $y += 1;
        }
        if (($_m + $addMonth) < 1) {
            $y -= 1;
        }

        // 月を足し算した後の日の処理
        $_lastDay = static::from($y, $m)->getLastDayOfMonth()->getDay();


        $d = ($_d == $lastDay) ? $_lastDay : ($_lastDay > $_d) ? $_d : $_lastDay;

        $this->modify($y . "-" . str_pad($m, 2, "0", STR_PAD_LEFT) . "-" . str_pad($d, 2, "0", STR_PAD_LEFT));
        return $this;
    }

    public function setMonth($m)
    {
        $y = $this->getYear();
        $d = $this->getDay();
        $dt = static::from($y, $m)->setDay($d);
        $this->modify($dt->format("Y-m-d"));
        return $this;
    }

    public function isSameDate(\DateTime $dt)
    {
        return $dt->format('Y-m-d') === $this->format('Y-m-d');
    }
    public function isAfterDate(\DateTime $dt)
    {
        return $dt->setTime(0, 0) < $this->setTime(0, 0);
    }
    public function isBeforeDate(\DateTime $dt)
    {
        return $dt->setTime(0, 0) > $this->setTime(0, 0);
    }

    public function dateLoop(\DateTime $to, $fn)
    {
        $diff = intval($this->diff($to)->format('%R%a'));
        $_d = clone $this;
        for ($i = 0; $i <= $diff; $i++) {
            call_user_func($fn, (clone $_d), $i);
            $_d->modify("+1 days");
        }
    }

    public function floorMinute($val)
    {
        $hour = $this->getHour();
        $min = $this->getMinute();
        $mod = $min % $val;
        $min -= $mod;
        $this->setTime($hour, $min, 0);
        return $this;
    }

    public function ceilMinute($val)
    {
        $hour = $this->getHour();
        $min = $this->getMinute();
        $mod = $min % $val;
        if ($mod != 0) {
            $min += ($val - $mod);
        }

        $this->setTime($hour, $min, 0);
        return $this;
    }
}
