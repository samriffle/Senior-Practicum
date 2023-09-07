# Senior-Practicum
A collection of my work on my senior practicum, continuing from the work I presented during summer research from 2022-2023 / 2023-2024

CSC-4081 Computer Applications Practicum I

Project Proposal: Developments Into the Small Scale Implementation of Dr. Robbeloth's Obstruction Research Processor Services Branch
Sam Riffle

1. PURPOSE AND SCOPE 

This system will be developed to allow a springboard for others when delving into further research when it comes to DNN based database systems and operations with datasets as it pertains to computer vision. The goal of this specific implementation is to construct a prototype implementation for experimental purposes, to be used by future SPUR sessions at MVNU.

3. FUNCTIONAL DESCRIPTION 

<p>The top level user interactable framework will include a web page with options to export image sets to our CS server at MVNU, and include all command line arguments as selectable options and text boxes. A wiki will be provided to assist in its use.</p>
<p>The HTF5 database itself will include storage options for raw image data imported from the user interactable framework (UIF), processed data from images that have gone through the preprocessor service.</p>
<p>The backend will include a small scale implementation of Dr. Robbeloth't obstruction code in Java pertaining to all code necessary to implement a DNN matching schema on obstructed images. Modules of code will handle CLA's, preprocesing, storage/retrieval from the database, and matching. All command line options selected by the UIF will go through the CLA (command line argument) option module in order to trigger the correct sequence of operations between the remaining 3 backend modules, database, and UIF.</p>

5. CONSTRAINTS 

<p>•	available resources: HTF5 is not covered in the database course at MVNU. At the time of this project proposal, Sam Riffle has just begun the database course: FA2023. There is no official course on machine learning or CUDA at MVNU, and as is such, all gpu and DNN work is subject to errors.</p>
<p>•	required interfaces: A PHP framework falls in line with many MVNU webpages currently in service. HTF5 will serve as a framework to store images in a filesystem-esque structure, where there will be root > datasets > imagesets > images. DNN will be carried out on cuda.</p>
<p>•	schedule requirements: The system should be available at the end of the SP23 semester. Any relevant constraints will be related to student availability and expertise. The FA23 schedule finds that the student courseload is at 17-18 credit hours. Time will be allotted to this practicum, but not at the expense of these credit hours.</p>
