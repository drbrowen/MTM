This is the very simple Object Relational Mapping code written for the
administrative database.


To generate the database include files from the existing XML file, use
the following code:

$ php5 make_dblayer.php <xml config file>

This will generate a new directory with the same name as the schema
name from the xml file.  If the directory already exists, the script
fails and will not do anything.  Otherwise, it will then create a
separate file for each table and a general include file in this
subdirectory.  Generally, you probably want to move the directory to
an appropriate place from the current directory.  You only need to
include the schema name file, such as

include_once "ADMINDATABASE/admindatabase.php";

This will include all tables.

--
To change table structure in the database, first change the live
database using whatever tools, such as MySQL Workbench or the mysql
command line utility.  


Then edit the XML file to update the various attributes.  The
top-level element should be <dbconfig>.  Under that should be one or
more <schema> sections.  Each <schema> section should be named with a
<name> tag.

Tables are stored under the <tables> section, each one getting a
<table> definition.  Tables have
2 names, the name of the table in the dabase itself, called <dbname>,
and the name of the PHP class to access this table called <classname>.
So, in the original usage, all of the table classes in php were
prefixed with T_.

The primary key of the table is noted with <primarykey>.  The system
is currently simple-minded enough that multi-column primary keys are
not allowed, nor are tables without primary keys (such as many-to-many
tables).

Foreign keys are listed under the <fkattributes> section with
<fkattribute>.  There are two subfields of a foreign key in the XML
schema.  The <fk> field is the foreign key column name in the
database.  The <pt> field is the pointer to the _class_ name (not
database table name) where the foreign key points.  Foreign keys are
only allowed to point to the primary key of other tables.

Non-primary key and non-foreign key columns are listed under the
<attributes> section as <attr>.  Currently the system doesn't know
squat about data types, so it is up to the user to get this correct.

Example:

<dbconfig>
  <schema>
    <!-- This will create a subdirectory called "DBname -->
    <name>DBname</name>
    <tables>
      <table>
        <!-- In the database, the table is called "User" -->
        <dbname>User</dbname>
	<!-- We want a php class called T_User to access the User table -->
        <classname>T_User</classname>
	<!-- The primary key is the single column "ID" -->
        <primarykey>ID</primarykey>
	<!-- There are two more columns called "name" and "NetworkID" -->
        <attributes>
          <attr>name</attr>
	  <attr>NetworkID</attr>
        </attributes>
      </table>
      <table>
        <!-- Another table called "Phone" -->
	<dbname>Phone</dbname>
	<!-- Just for the heck of it, we want the PHP class called  FRED -->
	<classname>FRED</classname>
	<!-- the primary key is the column "ID", I know original. -->
	<primarykey>ID</primarykey>
	<!-- This has a foreign key to point to the User table. -->
	<fkattributes>
	  <!-- We point it to the _class_ name.  -->
	  <fkattribute><fk>User_ID</fk><pt>T_User</pt></fkattribute>
        </fkattributes>
	<!--- And the phone number -->
	<attributes>
	  <attr>phone</attr>
	</attributes>
      </table>
    </tables>
  </schema>
</dbconfig>


