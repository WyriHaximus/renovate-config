# renovate-config

Renovate shared config

## Top level included presets

These top level presets are used as a one-stop preset for 80%+ of packages that don't need anything fancy.

| Name        | Usage                                                                |
|-------------|----------------------------------------------------------------------|
| default     | No recommended but includes all package rules                        |
| php-package | For PHP packages using on public repos                               |
| php-project | For PHP projects using on private repos with limited Actions credits |

## Usage

```json
{
  "$schema": "https://docs.renovatebot.com/renovate-schema.json",
  "extends": [
    "github>WyriHaximus/renovate-config:php-package"
  ]
}
```

## Bottom level included presets

These bottom level presets are to be used to created configurations for more advanced setups.

| Name    | Usage                                                                                                                             |
|---------|-----------------------------------------------------------------------------------------------------------------------------------|
| base    | Included scheduling, Europe/Amsterdam timezone, and some base extends                                                             |
| package | No limitations to how often en how many PR's are created                                                                          |
| project | Limits to one PR per how and 3 PR's total to prevent lots of rebasing across many PR's                                            |
| php     | For PHP repositories including labels, a set of package rules, and grouping so certain groups of packages get updated all at once |

## License ##

Copyright 2023 [Cees-Jan Kiewiet](http://wyrihaximus.net/)

Permission is hereby granted, free of charge, to any person
obtaining a copy of this software and associated documentation
files (the "Software"), to deal in the Software without
restriction, including without limitation the rights to use,
copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following
conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
OTHER DEALINGS IN THE SOFTWARE.
