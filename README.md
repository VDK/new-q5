# new Q5
<span style="margin-top:0px; font:small">[Hosted on Toolforge](https://new-q5.toolforge.org/)</span>

new Q5 is a form that quickly set up a Wikidata item for an individual by generating a [QuickStatement](https://tools.wmflabs.org/quickstatements/#/batch) with the basic parameters such as name and age.


## What you can do with new Q5

* Calculate approximate DOB if the age at DOD is given.
* Calculate approximate DOB if the age and publication date for the provided reference is given.
* Calculate the DOD if the input is like "last Friday" or "last November" with the publication date of the reference or, if not provided, the current date as its benchmark.
* Retrieve the reference's meta data using the [Citoid API](https://en.wikipedia.org/api/rest_v1/#/Citation/getCitation).
* Match the difference words in a person's name to Q-items in Wikidata. This uses the most basic algorithm you can imagine (given name + given name + family name).
* Add a reference to a custom, pipe seperated, QuickStatement.
* Add the provided reference as a "[described at URL (P973)](https://www.wikidata.org/wiki/Property:P973)" satement optionally.
* Add the DOB/DOD/custom QuickStatment to an existing instance of a person in Wikidata.

The user can edit the generated QuickStatement before importing it to Wikidata throught the QuickStatement app by selecting "Import QuickStatment".

## Disclaimer:
* New Q5 has not been tested with names not written in the Latin alphabet.
* New Q5 assumes a person's gender based on their first given name.
* It is assumed this form is used to create or update one item at the time and that the user will review if the changes made correspond with their expectations. 
* The script most commonly fails when it tries to break up a person's name into its constituent parts. It even fails on my own Dutch family name ("de Kok")! 

