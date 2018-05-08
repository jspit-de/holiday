# holiday

A php class for determining holidays for many countries, regions and languages.
All defintions are in a small SQLite database that can be changed and expanded by the user.
The database "JspitHoliday.sqlite" contains only examples and can be used without any claim to correctness and completeness.

## Usage

Include class JspitHoliday (1 File) directly with require or use a autoloader.

```php
<?php
$holiday = new JspitHoliday("DE-BB","JspitHoliday.sqlite"); 
$holidayList = $holiday->holidayList(2018,'en')

```
$holidayList contain a array with all public holidays from 
country Germany(de) Region Brandenburg (bb) with english names(en) for the year 2018.

```php
array (
  '2018-01-01' => "New Year's Day",
  '2018-03-30' => "Good Friday",
  '2018-04-01' => "Easter",
  '2018-04-02' => "Easter Monday",
  '2018-05-01' => "Labor Day",
  '2018-05-10' => "Ascension of Christ",
  '2018-05-20' => "Whit Sunday",
  '2018-05-21' => "Whit Monday",
  '2018-10-03' => "Day of German Unity",
  '2018-10-31' => "Reformation Day",
  '2018-12-25' => "Christmas Day",
  '2018-12-26' => "Boxing Day",
)
```
Regions can be nested to depth 3. The notation is based on the ISO standard 3361.
Examples: "DE", "NL", "DE-BY", "DE-BY-SCH-A"
Last for Germany-Bavaria-Schwabing-Augsburg(City). 
The languages can be divided into dialects, e.g. 'de-DE' , 'de-CH'.

Further examples:

```php
$dateTime = new DateTime("1 May 2018 08:00");

$holidaysDE = JspitHoliday::create("DE","JspitHoliday.sqlite");
if($holidaysDE->isHoliday($dateTime)) {
  echo "1 May 2018 is in DE a holiday";
}

//holidayName
$holidayName = $holidaysDE->holidayName('3 Oct','en');
if($holidayName) {
  echo $holidayName . "<br>";
  //'Day of German Unity'
}

//holidayNameList
$holidaysIL = JspitHoliday::create("IL","JspitHoliday.sqlite");
$list = $holidaysIL->holidayNameList("Pessach I",2018,2022,'de');

var_dump($list);
/*
array(5) {
  ["2018-03-31"]=>
  string(9) "Pessach I"
  ["2019-04-20"]=>
  string(9) "Pessach I"
  ["2020-04-09"]=>
  string(9) "Pessach I"
  ["2021-03-28"]=>
  string(9) "Pessach I"
  ["2022-04-16"]=>
  string(9) "Pessach I"
}
*/

/*
 * get Config from a URL
 */
$url = "http://example.com/data/JspitHoliday.sqlite";
$tmpfname = tempnam(sys_get_temp_dir(), "holiday.sqlite");
$copyOk = copy($url,$tmpfname);

$holidaysDE = new JspitHoliday('de',$tmpfname);


```


## Define country depending holidays

All holidays are dates defined in the table 'holidays' and names for all languages in the table 'names'.

### Fields of  holidays table:

| Field | Description |
| ----- | ----------- |
| id | id, autoincrement, reference to 'idholiday' in the table names |
| comment | a comment (not a name for a holiday) |
| year | free or "*" for all years, a year YYYY for only this year, -YYYY to year, YYYY- from year, a range YYYY-YYYY, a list of years YYYY,YYYY,.. |
| except_year | free for no exception, YYYY for except only this year, except a range YYYY-YYYY, except a list of years YYYY,YYYY,.., "*" except all |
| month | used for fixed months |
| day | used for fixed days |
| special | A pipe with relative date formates and wildcards. Pipe elements are are separated by \| . {{name}} is a wildcard. Some examples: "first sunday of september {{year}}\|next thursday" ,'third sunday of september {{year}}' , '{{easter}}\|+1 Day' |
| region | A list auf Countrycodes/Regions. Countrycode-[[[Subdivision]-Subregion1]-Subregion2] |
| typ | Type of holiday (TYPE_OFFICIAL, TYPE_BANK..) |

### Fixed date every year

Table holidays

| id  | comment     | year | except_year | month | day | special | region      | typ |
| --- | ----------- | ---- | ----------- | ----- | --- | ------- | ----------- | --- | 
| 1   | NewYear     |      |             | 1     | 1   |         | DE,CH,AT,NL | 1   |

Table names

| id  | idholiday   | language | name           | 
| --- | ----------- | -------- | -------------- | 
|     | 1           | en-GB    | New Year's Day | 
|     | 1           | de-DE    | Neujahr        | 
|     | 1           | de-CH    | Neujahr        |
|     | 1           | ru-RU    | Новый год      |

### Fixed date for a year or rage of years

Table holidays

| id  | comment         | year  | except_year | month | day | special | region | typ | 
| --- | --------------- | ----- | ----------- | ----- | --- | ------- | ------ | --- |  
|     | Reformation Day | 2017  |             | 10    | 31  |         | DE     | 1   |
|     | Day of Unity    | 1990- |             | 10    | 3   |         | DE     | 1   |

### Movable dates

Table holidays

| id  | comment        | year | except_year | month | day | special                            | region | typ |
| --- | -------------- | ---- | ----------- | ----- | --- | ---------------------------------- | -------| --- |  
| 7   | Buß und Bettag |      |             | 11    | 23  | last Wed                           | DE-SN  | 1   |
| 23  | Bettag         |      |             |       |     | third sunday of september {{year}} | CH     | 1   |


### Dates depend on religious holidays

You can use this wildcards:

{{easter}}    Catholic Easter
{{easter_o}}  Orthodox Easter
{{passover}}  Passover I

Others can be defined in an extension class.

Table holidays

| id  | comment     | year | except_year | month | day | special             | region      | typ | 
| --- | ----------- | ---- | ----------- | ----- | --- | ------------------- | ----------- | --- |  
| 8   | Ascension   |      |             |       |     | {{easter}}\|+39 Days | DE,CH,AT,NL | 1   |


Table names

| id  | idholiday   | language | name                | 
| --- | ----------- | -------- | ------------------- | 
|     | 8           | en-GB    | Ascension of Christ | 
|     | 8           | de-DE    | Christi Himmelfahrt | 
|     | 8           | de-CH    | Auffahrt            |

### Dates with filter conditions

If a holiday is Sunday (or weekend), then in some countries  a substitute day in the following week is an additional holiday.
Example: 
The 5th of May is Children's Day in Japan. Is the 5th of May a Sunday (or the 6th of May a Monday),
then the 6th of May is a holiday. You can define this date with a filter condition.

Table holidays

| id  | comment        | year | except_year | month | day | special             | region | typ | 
| --- | -------------- | ---- | ----------- | ----- | --- | ------------------- | ------ | --- |  
|     | Childrens Day  |      |             |  5    | 5   |                     | JP     | 1   |
|     | Childrens Day+ |      |             |  5    | 6   | {{?D=Mon}}          | JP     | 2   |

### Dates with movement conditions

A holiday date will be postponed under certain conditions. 
If an operation is noted after the condition, then it will only be executed if the condition is true.
Example for special entry: {{?D=Thu}}+1 Day

### Dates without formula

Some dates of holidays can not be described by a rule or it is too difficult to do that.
For these cases, the date must be set for each year. With a special wildcard you can create a list for next years.

| id  | comment          | year      | except_year | month | day | special               | region | typ | 
| --- | -----------------| --------- | ----------- | ----- | --- | --------------------- | -------| --- |  
| 35  | Independence Day | 2018-2020 |             |       |     | {{2018:4/19,5/9,4/29}}| IL     | 1   |

## Requirements

PHP 5.4 - 7.2
