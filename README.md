# Grid Group Bundle

Under development!

A bundle to manipulate a set of CSV files as a database.  The idea is to avoid having to create temporary entities or models during the data import.

## CsvParser
The [CSV Reader](https://csv.thephpleague.com/9.0/reader/) class is an excellent way to read records from a database.

```php
$csv = Reader::createFromPath('/path/to/file.csv', 'r');
$csv->setHeaderOffset(0);
$header_offset = $csv->getHeaderOffset(); //returns 0
$header = $csv->getHeader(); //returns ['First Name', 'Last Name', 'E-mail']

```
    
## Example: create a simple related table.

Given a movie CSV table, create a CsvDatabase (GridGroup?)

```
id,title,poster,overview,release_date,genres
287947,Shazam!,https://image.tmdb.org/t/p/w500/xnopI5Xtky18MPhK40cZAGAOVeV.jpg,"A boy is given the ability to become an adult superhero in times of need with a single magic word.",1553299200,"Action, Comedy, Fantasy"
299537,"Captain Marvel",https://image.tmdb.org/t/p/w500/AtsgWhDnHTq68L0lLsUrCnM7TjG.jpg,"The story follows Carol Danvers as she becomes one of the universe’s most powerful heroes when Earth is caught in the middle of a galactic war between two alien races. Set in the 1990s, Captain Marvel is an all-new adventure from a previously unseen period in the history of the Marvel Cinematic Universe.",1551830400,"Action, Adventure, Science Fiction"
```

Create a movie.csv and genre.csv, with genre counts as an extra field

We need a schema to define the output csv database, which we can create by naming the fields.

```php

$gridGroup = new GridGroup('movie_and_genre');
$grid = (new \Survos\GridGroupBundle\Model\Grid('movie'));
$grid->addRow()




```
