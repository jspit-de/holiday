<?php
//07.08.2018
//error_reporting(-1);
error_reporting(E_ALL ^ (E_WARNING | E_USER_WARNING));
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=UTF-8');

require __DIR__ . '/../class/class.debug.php';
require __DIR__ . '/../class/JspitHoliday.php';
require __DIR__ . '/../class/phpcheck.php';

$t = new PHPcheck;

//prepare Tests
class ExtHoliday extends JspitHoliday{
  public $config;

  //empty construct create instance without config-DB
  public function __construct() {
  
  }  
  
  public function getDateFromDBrow($row, $year, $month = 1, $day = 1){
    return parent::getDateFromDBrow($row, $year, $month, $day); 
  }
}

//Tests
$t->start('exist versions info');
$info = $t->getClassVersion("JspitHoliday");
$t->check($info, !empty($info));

$t->start('create class');
$holiday = new ExtHoliday();
$t->check($holiday, $holiday instanceof JspitHoliday);

$t->start('getDateFromDBrow fix Date'); 
$row = (object)(array(
     'id' => "18",
     'year' => "",
     'except_year' => "",
     'month' => "10",
     'day' => "31",
     'special' => "",
     'region' => "DE",
  ));
$result = $holiday->getDateFromDBrow($row, 2018, 1, 1);
$t->checkEqual($result, "2018-10-31");

$t->start('getDateFromDBrow except_year 2018'); 
$row->except_year = "2018";
$result = $holiday->getDateFromDBrow($row, 2018, 1, 1);
$t->checkEqual($result, false);

$t->start('getDateFromDBrow only year 2018'); 
$row->except_year = "";
$row->year = "2018";
$result = $holiday->getDateFromDBrow($row, 2018, 1, 1);
$t->checkEqual($result, "2018-10-31");

$t->start('getDateFromDBrow only year 2018+2019'); 
$row->except_year = "";
$row->year = "2018,2019";
$result = "";
for($i=2015; $i<=2020; $i++){
  $result .= ",".$holiday->getDateFromDBrow($row, $i, 1, 1); 
}
$t->checkEqual($result, ",,,,2018-10-31,2019-10-31,");

$t->start('getDateFromDBrow only year 2017..2019'); 
$row->except_year = "";
$row->year = "2017-2019";
$result = "";
for($i=2015; $i<=2020; $i++){
  $result .= ",".$holiday->getDateFromDBrow($row, $i, 1, 1); 
}
$t->checkEqual($result, ",,,2017-10-31,2018-10-31,2019-10-31,");

$t->start('getDateFromDBrow year from 2018'); 
$row->except_year = "";
$row->year = "2018-";
$result = "";
for($i=2015; $i<=2020; $i++){
  $result .= ",".$holiday->getDateFromDBrow($row, $i, 1, 1); 
}
$t->checkEqual($result, ",,,,2018-10-31,2019-10-31,2020-10-31");

$t->start('getDateFromDBrow year to 2018'); 
$row->except_year = "";
$row->year = "-2018";
$result = "";
for($i=2015; $i<=2020; $i++){
  $result .= ",".$holiday->getDateFromDBrow($row, $i, 1, 1); 
}
$t->checkEqual($result, ",2015-10-31,2016-10-31,2017-10-31,2018-10-31,,");

$t->start('check conditional modify Sat->Mon');
$row->year = "";
$row->except_year = "";
$row->month = "";
$row->day = "";
$row->special = "{{?D=Sat,sun}}next Monday";

$result = $holiday->getDateFromDBrow($row, 2018, 5, 5); 
$t->checkEqual($result, "2018-05-07");

$t->start('check conditional modify Sun->Mon');
$result = $holiday->getDateFromDBrow($row, 2018, 5, 6); 
$t->checkEqual($result, "2018-05-07");

$t->start('check conditional modify Fri not');
$result = $holiday->getDateFromDBrow($row, 2018, 5, 4); 
$t->checkEqual($result, "2018-05-04");

$t->start('check conditional modify Fri false');
//no modifier after }}
$row->special = "{{?D=Sat,sun}}";
$result = $holiday->getDateFromDBrow($row, 2018, 5, 4); 
$t->checkEqual($result, false);

$t->start('check conditional modify if fri');
//no modifier after }}, 4.5.2018 = Fri
$row->special = "{{?D=fri}}";
$result = $holiday->getDateFromDBrow($row, 2018, 5, 4); 
$t->checkEqual($result, "2018-05-04");

$t->start('check conditional modify not fri');
//no modifier after }}, 4.5.2018 = Fri, condition not 
$row->special = "{{?D!=fri}}";
$result = $holiday->getDateFromDBrow($row, 2018, 5, 4); 
$t->checkEqual($result, false);

$t->start('check easter modify');
$row->special = "{{easter}}";
$result = $holiday->getDateFromDBrow($row, 2018); 
$t->checkEqual($result, "2018-04-01");

$t->start('check Ash Wednesday 2018-2022');
$row->special = "{{easter}}-46 Days";
$result = array();
for($y=2018; $y<= 2022; $y++){
  $result[] = $holiday->getDateFromDBrow($row, $y); 
}
$result = implode(',',$result);
$expected = "2018-02-14,2019-03-06,2020-02-26,2021-02-17,2022-03-02";
$t->checkEqual($result, $expected);

$t->start('check islamic(hijri) holiday');
$row->month = 10;  //Ramadan-Fest
$row->day = 1;
$row->special = "{{islamic}}";
$result = $holiday->getDateFromDBrow($row, 2018); 
$t->checkEqual($result, "2018-06-15");

$t->start('check(2) islamic holiday');
$row->month = 12;  
$row->day = 10;
$row->special = "{{islamic}}";
$result = $holiday->getDateFromDBrow($row, 2018); 
$t->checkEqual($result, "2018-08-22");

$t->start('check indep.day IL 2018');
$row->month = 9;  
$row->day = 4;
$row->special = "{{hebrew}}|{{?D=Tue}}+1 Day|{{?D=Fri}}-1 Day";
$result = $holiday->getDateFromDBrow($row, 2018); 
$t->checkEqual($result, "2018-04-19");

$t->start('check indep.day IL 2020');
$row->month = 9;  
$row->day = 4;
$row->special = "{{hebrew}}|{{?D=Tue}}+1 Day|{{?D=Fri}}-1 Day";
$result = $holiday->getDateFromDBrow($row, 2020); 
$t->checkEqual($result, "2020-04-29");

$t->start('check indep.day IL 2021');
$row->month = 9;  
$row->day = 4;
$row->special = "{{hebrew}}|{{?D=Tue}}+1 Day|{{?D=Fri}}-1 Day";
$result = $holiday->getDateFromDBrow($row, 2021); 
$t->checkEqual($result, "2021-04-15");

//test if intl exists
if(extension_loaded('intl')) {
  
$t->start('Chinese new year 2018');
$row->month = 1;  
$row->day = 1;
$row->special = "{{chinese}}";
$result = $holiday->getDateFromDBrow($row, 2018); 
$t->checkEqual($result, "2018-02-16");

$t->start('Chinese new year 2019');
$row->month = 1;  
$row->day = 1;
$row->special = "{{chinese}}";
$result = $holiday->getDateFromDBrow($row, 2019); 
$t->checkEqual($result, "2019-02-05");

}

//Ausgabe 
echo $t->getHtml();

