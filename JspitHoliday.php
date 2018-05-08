<?php
/**
.---------------------------------------------------------------------------.
|  Software: JspitHoliday - PHP class                                       |
|   Version: 1.25                                                           |
|      Date: 2018-05-08                                                     |
| ------------------------------------------------------------------------- |
| Copyright © 2018, Peter Junk alias jspit All Rights Reserved.             |
'---------------------------------------------------------------------------'
*/

class JspitHoliday
{
  const TYPE_OFFICIAL = 1;
  const TYPE_BANK = 2;
  const TYPE_OBSERVED = 4;
  const TYPE_OTHER = 8;
  const TYPE_4 = 16;
  const TYPE_5 = 32;
  const TYPE_6 = 64;
  const TYPE_ALL = 0x7FFF;
  
  protected $pdo;
  protected $language = "de-DE";
  protected $region;
  protected $config;
  protected $typFilter;

  /**
   * Constructs the class instance
   * @param string $filterRegion Country/Region ISO 3361 Alpha2 ('DE','DE-BY'..) 
   * @param string $sqliteFile filename for SQLite, default: holiday.sqlite
   * @param int $typFilte Filter for Holiday-Type for SQLite, default: holiday::TYPE_ALL
   * @throws InvalidArgumentException
   */
  public function __construct($filterRegion = "", $sqliteFile = null, $typFilter = self::TYPE_ALL) {
    //verify filter
    $filter = strtoupper($filterRegion);
    if(!preg_match('/^[A-Z]{2,3}(-[A-Z0-9]{1,8}){0,3}$/', $filter)) {
      throw new InvalidArgumentException("filterRegion is not like ISO3361");  
    }
        
    if(! is_string($sqliteFile) OR $sqliteFile == "") {
      $sqliteFile = __DIR__ . "/".basename(__CLASS__).".sqlite";
    }
    if(!file_exists($sqliteFile)){
      throw new InvalidArgumentException("SQLite File '$sqliteFile' not found");
    }
    try{
      $options = array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
      );

      $this->pdo = new PDO('sqlite:'.$sqliteFile,null,null,$options);
      
      $this->createConfig($filterRegion, $typFilter);
    } catch(Exception $e) {
      throw new InvalidArgumentException("Faulty SQLite-DB '$sqliteFile'");  
    }
    
  }
  
 /**
  * return a new class instance
  * @param string $filterRegion Country/Region ISO 3361 Alpha2 ('DE','DE-BY'..) 
  * @param string $sqliteFile filename for SQLite, default: holiday.sqlite
  * @param int $typFilte Filter for Holiday-Type for SQLite, default: holiday::TYPE_ALL
  * @return object JspitHoliday
  * @throws InvalidArgumentException
  */
  public static function create($filterRegion = "", $sqliteFile = null, $typFilter = self::TYPE_ALL) {
    return new static($filterRegion, $sqliteFile, $typFilter );    
  }
  
  
 /**
  * set Default Language
  * @param string $language p.E. "de-DE", "en-GB" 
  * @return $this
  */
  public function setLanguage($language = "de-DE") {
    $this->language = $language;
    return $this;
  }

 /**
  * get Default Language
  * @return string default language
  */
  public function getLanguage() {
    return $this->language;
  }

 /**
  * set region
  * @param string $filterRegion Country/Region ISO 3361 Alpha2 ('DE','DE-BY'..) 
  * @return $this
  */
  public function setRegion($filterRegion) {
    try{
      $this->createConfig($filterRegion, $this->typFilter);
      return $this;
    } catch(Exception $e) {
      throw $e;
    }
  }
  
 /**
  * Returns the current Region
  * @return string
  */
  public function getRegion() {
    return $this->region;
  }
  
 /**
  * set Filter Holiday Type
  * @param int typ Filter
  * @throws Exception
  */
  public function setTypFilter($typFilter = self::TYPE_ALL) {
    try{
      $this->createConfig($this->region, (int)$typFilter);
      return $this;
    } catch(Exception $e) {
      throw $e;
    }
  }

 /**
  * get Name from a Holiday p.e: "New Year's Day"
  * @param $date: string, datetime-object or timestamp 
  * @param $language string p.E. "de-DE", "en-GB" 
  * @return mixed string name if ok, false Error or Date is not a Holiday,
  *  string "?" no Name for the language in Database  
  */
  public function holidayName($date = "today", $language = null){
    if(is_string($date)) {
      $date = date_create($date)->format("Y-m-d");
    } elseif($date instanceof DateTime) {
      $date = $date->format("Y-m-d");
    } elseif(is_int($date)) {
      $date = date("Y-m-d", $date);
    } else {
      throw new InvalidArgumentException("incorrect Parameter date '$date' "); 
    }
    
    $id = $this->getId($date);
    if(!$id) return false;  //holiday by date not found
    
    if($language === null) $language = $this->language;
    $name = $this->getHolidayNameById($id, $language);

    return $name;
  }

 /**
  * return array( 'YYYY-MM-DD' => holidayname, ..)
  * the array is sorted by ascending date
  * @param integer year full year p.E. 2018
  * @param string $language p.E. "en_GB"
  * @return array
  */
  public function holidayList($year = null, $language = null){
    if(empty($year)) $year = date('Y');
    if($language === null) $language = $this->language;

    $hList = array();
    foreach($this->config as $id => $row){
      $curDate = $this->getDateFromDBrow($row, $year);
      if($curDate === false) continue;

      $hList[$curDate] = $this->getHolidayNameById($id, $language);
    }
    ksort($hList); 
    return $hList;
  }
  
 /**
  * return array of datetime objects
  * the array is sorted by ascending date
  * datetime objects are extended with public property holidayName
  * @param year integer full year p.E. 2018
  * @param $language string p.E. "en_GB"
  * @return array
  */
  public function dateTimeList($year = null, $language = null){
    $dtArr = array();
    foreach(self::holidayList($year, $language) as $strDate => $name) {
      $dt = date_create($strDate);
      if(is_object($dt)) {
        $dt->holidayName = $name;
        $dtArr[] = $dt;
      }
    }
    return $dtArr;
  }


 /**
  * return true id if date is a holiday or false
  * @param mixed $date string, datetime-object or timestamp 
  * @return bool
  */
  public function isHoliday($date = 'today'){
    if(is_string($date)) {
      $date = date_create($date)->format("Y-m-d");
    } elseif($date instanceof DateTime) {
      $date = $date->format("Y-m-d");
    } elseif(is_int($date)) {
      $date = date("Y-m-d", $date);
    } else {
      throw new InvalidArgumentException("date '$date' incorrect"); 
    }
    
    return $this->getId($date) ? true : false;
    
  }
  
 /**
  * get List of Names from DB as 
  * array(idholiday => name, ..) by nameFilter and language
  * return false if not found
  * @param string $nameFilter Filter for name , caseinsenitive 
  * @param string $language  how de or de-ch, default Default Language
  * @param bool $onlyCurrentRegion bool, default false
  * @return mixed  
  */
  public function getNames($nameFilter = "", $language = null, $onlyCurrentRegion = false) {
    if($language === null) $language = $this->language;
    $sql = "SELECT idholiday, name
      FROM names 
      WHERE language LIKE :language COLLATE NOCASE"; 
      $param = array("language" => "%".$language."%");
    if($nameFilter != "") {
      $sql .= " AND name LIKE :nameFilter COLLATE NOCASE";
      $param['nameFilter'] = "%".$nameFilter."%";
    }
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($param);
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    if($rows AND $onlyCurrentRegion) {
      $rows = array_intersect_key($rows, $this->config);  
    }
    return $rows ? $rows : false; 
  }
  
 /**
  * get List of Names from DB as 
  * array(idholiday => name, ..) by nameFilter and language
  * return false if not found
  * @param nameFilter: Filter for name , caseinsenitive 
  * @param yearStart: integer YYYY , default current year
  * @param countYears:  default 1 or end of year (end of year > 
  * @param language string how de or de-ch, default Default Language
  * @return mixed
  */
  public function holidayNameList($nameFilter = "",$yearStart = null, $countYears=1, $language = null) {
    if($yearStart === null) $yearStart = date("Y");
    //countYears or end of year
    if($countYears > 1000 AND $countYears >= $yearStart){
       $countYears = $countYears - $yearStart + 1; 
    }
    //get id => name array
    $idNames = $this->getNames($nameFilter, $language, true);
    if($idNames === false) return false;

    $list = array();
    for($i=0; $i < $countYears; $i++){
      $year = $yearStart + $i;
      foreach($idNames as $id => $HolidayName) {
        if(array_key_exists($id, $this->config)) {
          $curDate = $this->getDateFromDBrow($this->config[$id], $year);
          if($curDate) $list[$curDate] = $HolidayName;
        } else {  
        //id not in current region
        }
      }
    }  
    ksort($list);
    return $list;
  }
 
 /** 
  * get config, may use as debugging info
  * @return array
  */
  public function getConfig(){
    return $this->config;
  }
  
  // get id from holiday-Table
  // @param $strDate string format "YYYY-MM-DD"
  protected function getId($date){
    list($year,$month,$day) = explode("-",$date);
    foreach($this->config as $id => $row){
      if($date == $this->getDateFromDBrow($row, $year, $month, $day)) {
        return $id;
      }
    }
    return false;
  }
  
 /*
  * get date as string YYYY-MM-DD
  * $code string : special Code 
  * $year 1600 < year < 2100
  * return false if error
  */
  protected function getMovableDate($code, $year, $month = 1, $day = 1){
    $replacements = array(
      '{{year}}' => $year,
      '{{month}}' => $month,
      '{{day}}' => $day,
      '{{easter}}' => $this->getEasterDate($year),
      '{{easter_o}}' => $this->getEasterDate($year,true),
      '{{passover}}' => $this->getPassoverDate($year),
    );
    
    foreach($replacements as $key => $value){
      $code = str_replace($key, $value, $code); 
    }
    
    //check for datelist {{2018:2/3,..}}
    if(preg_match('~\{\{(\d{4}):(.*)\}\}~',$code,$match)) {
      $startYear = $match[1];
      $values = explode(",",$match[2]);
      $key = $year - $startYear; 
      if(array_key_exists($key,$values)) {
        $code = str_replace($match[0],$values[$key],$code);
      } else {
        return false;
      }
    }
    
    //check extends methods
    if(preg_match('~\{\{([a-z]+)\}\}~',$code,$match)) {
      $methodName = $match[1];
      if(method_exists($this, $methodName)) {
        $replacement = $this->$methodName($year, $month, $day);
        $code = str_replace($match[0],$replacement,$code);
      } else { //error
        throw new Exception("Error ".__CLASS__.": unknown special entry ".$match[0]); 
      }      
    }
    
    $modifiers = explode("|", $code);
    $date = date_create($year."-".$month."-".$day);
    foreach($modifiers as $modify) {
      if(preg_match('~^\{\{\?([DdmL]+)(!?=)([^}]+)\}\}(.*)~',$modify,$match)) {
        $curFmt = $date->format($match[1]);
        $found = stripos($match[3], $curFmt) !== false;
        if($found === ($match[2] == "=")) {
          //condition true
          if($match[4] !== "") $date->modify($match[4]);
        } elseif($match[4] === "") {
          return false;
        }
      } else { 
        $date->modify($modify);
      }        
      $errArr = date_get_last_errors();
      if($errArr['error_count']) return false;
    }
    
    return $date->format("Y-m-d");
   }
  
  //get easter-date as string YYYY-MM-DD
  protected function getEasterDate($year,$orthodox = false){
    if($orthodox) {
      $flag = CAL_EASTER_ALWAYS_JULIAN;
      $basisDate = $year."-4-3";
    } else {
      $flag = CAL_EASTER_ALWAYS_GREGORIAN;
      $basisDate = $year."-3-21";
    }
    $date = date_create($basisDate)
      ->modify(easter_days($year, $flag).' Days')
    ;
    return $date
      ->modify((-(int)$date->format('w'))." Days")
      ->format("Y-m-d");
  }
  
 /*
  * calculate the first day of Passover (Gauß)
  * @params: $year integer as YYYY, interval 1900 to 2099
  * @return date as string YYY-MM-DD
  */
  protected function getPassoverDate($year){
    $a = (12*$year+12)%19; 
    $b = $year%4;
    $m = 20.0955877 + 1.5542418 * $a + 0.25 * $b - 0.003177794 * $year; 
    $mi = (int)$m;
    $mn = $m-$mi;
    $c = ($mi + 3 * $year + 5 * $b + 1)%7; 
    if($c==2 OR $c==4 OR $c==6) {
      $mi += 1;
    } elseif($c==1 AND $a > 6 AND $mn >= (1367/2160)) {
      $mi += 2;
    } elseif ($c==0 AND $a > 11 AND $mn > (23269/25920)) {
      $mi += 1;
    }
    return date_create($year."-3-13")
      ->modify($mi." Days")
      ->format("Y-m-d"); 
  }  


 /*
  * return data as string YYYY-MM-DD or false
  */
  protected function getDateFromDBrow($row, $year, $month = 1, $day = 1){
    //accept years
    if(strlen($row->year) >= 4) {
      $dbEntry = $row->year;
      if(ctype_digit($dbEntry)) {
        //only YYYY
        if($dbEntry != $year) return false;
      } elseif(preg_match('~^\d{4}-$~',$dbEntry)) {
        //YYYY-
        if($year < (int)$dbEntry) return false;
      } elseif(preg_match('~^-\d{4}$~',$dbEntry)) {
        //-YYYY
        if($year > (-(int)$dbEntry)) return false;
      } elseif(!in_array($year,$this->listToArray($dbEntry))) {
        return false;
      }
    }
        
    if(strlen($row->except_year) >= 1) {
      if($row->except_year == '*') return false;
      if(in_array($year,$this->listToArray($row->except_year))){
        return false;
      }
    }

    $curmonth = $row->month ? $row->month : $month;
    $curday = $row->day ? $row->day : $day;
    if($row->special) {
      //getMovableDate return false if not match or error
      $curDate = $this->getMovableDate($row->special,$year,$curmonth,$curday);
    } else {
      $curDate = sprintf("%04d-%02d-%02d",$year,$curmonth,$curday);
    }
    return $curDate;
  }
  
  //create config-array
  private function createConfig($filterRegion, $typFilter){
    $sql = "SELECT id, year, except_year, month, day, special, region
      FROM holidays 
      WHERE typ & ".(int)$typFilter.
      " ORDER BY except_year DESC, year DESC";

    $stmt = $this->pdo->query($sql);
    
    $this->config = array();
    $match = false;
    foreach($stmt as $row){
      $dbRegios = explode(",",$row->region);
      foreach($dbRegios as $region) {
        if($match = (stripos($filterRegion,$region) === 0)) break;
      }
      if($match) $this->config[$row->id] = $row;
    }
    $this->region = $filterRegion;
    $this->typFilter = $typFilter;
    return true;
  }

  //string list to array
  private function listToArray($strList){
    $strList = preg_replace_callback(
      '/(\d{4})-(\d{4})/',
      function(array $m){
        return implode(",",range($m[1],$m[2]));
      },
      $strList
    );
    return explode(",",$strList);  
  }

  //get Name by id and $language
  private function getHolidayNameById($id, $language){
    $sql = "SELECT name 
      FROM names 
      WHERE idholiday = $id AND language LIKE :language COLLATE NOCASE
      LIMIT 1";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(array("language" => "{$language}%"));
    $row = $stmt->fetch();
    if($row) return $row->name;
    return "?";
  }
  
  
  
}  
