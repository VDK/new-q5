# new Q5
<span style="margin-top:0px; font:small">[Hosted on Toolforge](https://new-q5.toolforge.org/)</span>

Quickly set up a Wikidata item for an individual with this form that generates a [QuickStatement](https://tools.wmflabs.org/quickstatements/#/batch) with the basic parameters.


## Functionality

* Calculates approximate DOB if the age at DOD is given.
* Calculates approximate DOB if the age and publication date for the provided refference is given
* Calculates the DOD if the input is like "last Friday" or "last November" with the publication date of the refference or, if not provided, the current date as its benchmark
* Retrieves the reference's meta data using the [Citoid API](https://en.wikipedia.org/api/rest_v1/#/Citation/getCitation)
* Match the difference words in a person's name to Q-items in Wikidata. This uses the most basic algorithm you can imagine (given name + given name + family name). This functionality has not been tested with names not written in the Latin alphabet.
* Assumes a person's gender based on their first given name
* Add a reference to a custom, pipe seperated, QuickStatement
* Add the provided reference as a "[described at URL (P973)](https://www.wikidata.org/wiki/Property:P973)" satement optionally
* Add the DOB/DOD/custom QuickStatment to an existing instance of a person in Wikidata

The user can edit the generated QuickStatment before importing it to Wikidata throught the QuickStatement app by selecting "Import QuickStatment" 

It is assumed this form is used to create or update one item at the time and that the user will review if the changes made correspond with their expectations. The script most commonly fails when it tries to break up a person's name into its constituent parts. It even fails on my own Dutch family name (de Kok)! 

