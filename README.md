# csv2json

https://gist.github.com/f2r/2f1e1fa27186ac670c21d8a0303aabf1

# Usage

```sh
./csv2json <path/to/file.csv> [--pretty] [--fields "field1;field2;..."] [--aggregate field] [--desc path/to/desc/file.ini]
```

# Notes

* No guess on delimiter yet, only works with `;` !
* Overkill OO implementation inspired by https://www.elegantobjects.org/

## TODO

* [x] pretty json
* [ ] desc
* [x] fields
* [x] aggregate
* [ ] guess csv delimiter
* [ ] fields argument delimiter
* [ ] test missing file argument
* [ ] test incorrect file (eg: rows too short)

## WONT DO

* autoloader

## Tests

IMPLEMENTATION=oo ./unit-test
