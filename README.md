# Senior-Practicum
A collection of my work on my senior practicum

CSC-4081 Computer Applications Practicum I

Project Proposal: Development a reservation system for Thorne Library study rooms as MVNU
Sam Riffle

1. PURPOSE AND SCOPE 

This system will be used to allow students to view and reserve available rooms for study at any given date in MVNU’s Thorne Library foremost, with a module to allow administrators to update the present schedule as rooms are closed or opened. Users will involve students, library staff, and additional maintenance staff, where the stakeholders include Tim Radcliff of MVNU and Travis Kennedy, also of MVNU staff. The goal of this project will be to deliver a minimally viable product for use with library reservations separate from the 3rd party application currently deployed. 

2. FUNCTIONAL DESCRIPTION 

<p>The system will provide the ability to reserve a room for study at any given point within the school semester provided a fine of $5 is not present on a student account, as well as cancel a reservation. Input will include user selection of an available room, user selection of room configurations, the date and time a room will be reserved given in 30 minute reservation increments, and user ID for authentication. Output will include a confirmation submenu of room choice, room configuration, and datetime before authentication, then output of confirmation when a room is successfully reserved after confirmation. Interfaces will include a web page for reservation and a database storing dates and configurations available, and an API interface for authentication where the user will be redirected. </p>
<p>The system will provide the ability for an administrator to add or remove available rooms for any given date(s), as well as add or remove room configuration options for any given room available on any given date(s). Additionally, it will provide administrators with the ability to mark rooms as in use and finished when room keys are issued and received at the front desk of Thorne Library, as well as reset fines when they are paid so rooms may once again be accessed. Inputs will include user selection of rooms (with room configurations) along with a date and time to add or remove respective to every option stated in accordance with a room or date. Inputs will additionally include an option for reservation key issuance and acceptance, as well as the option to input transactions put forth to resolve fines. Outputs will include confirmation of changes to a room or dates, as well as confirmation of fine correction and room activity at the current time when a key is issued. Interfaces will include a web page for administrator usage and a database storing dates and configurations available, and an API interface for authentication where the administrator will be redirected, since everyone needs to be authenticated to use the web page in a secure fashion. </p>
<p>The system will autonomously issue $1 an hour fines for rooms not marked as finished from use, up to $5 in fines, as well as clear a reservation slot for a room that has not been marked as in use after 10 minutes have passed. There will be no input, and the student ID must reserve a new timeslot for a study room. Interfaces will include a database for retrieving current reservation information, as well as an API to add fines to a user account and check fines. </p>
<p>The system will autonomously update and show available and reserved rooms with their configuration options for the current day on a monitor in Thorne Library according to the database status. Inputs will not be required by a user, while outputs will include information pertaining to reservations on the monitor. Interfaces will include a database for retrieving current reservation information. </p>

3. CONSTRAINTS 

<p>Available resources include faculty information on current similar systems implemented at MVNU including but not limited to The Rec’s reservation system and systems that use MVNU’s redirect page for authentication for students and faculty when signing onto campus intranet. </p>
<p>External environmental factors that may affect usability include mentions of updating the current Thorne Library Website API within 1-2 years after this project is implemented, possibly rendering it obsolete. </p>
<p>The system must interact with an authentication page tied to MVNU’s student ID's to pass credentials around to handle student fines for current and future reservations. 
<p>The minimally viable system should be available by the end of the SP2024 semester</p>
