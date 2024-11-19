# Evento Course Creation
Plugin for Creation of empty moodle courses in a definied category based on the events in the
Evento application. 
Creating courses with
   * cours name like the Evento Module name
   * course shortname like the Evento "Anlassnummer" without "mod."
   * course startdate like the begin in Evento
   * coure enddate like the end date in Evento
   * course idnumber like the Evento "Anlassnummer"
   * Added course Evento enrolment method

Only include Evento modules to create moodle courses, which are editded at most one year ago and which have the start date in the future.
 
 Additional Features:
   * Option to create several course of studies in one category (deprecated)
   * Option to create common courses for multiple Evento events (deprecated)

# Config
Requires the local_evento plugin for the webservices access to Evento.
   * Set start and end time for the spring and autumn term course creation
   * Set an option to execute only on one specific day
   * Set some default course settings for new courses
   * Set a template course

## License
* Copyright (C) HTW Chur

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details:
http://www.gnu.org/copyleft/gpl.html
