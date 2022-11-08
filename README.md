# Terminus Get New Relic Data

[![Terminus v2.x Compatible](https://img.shields.io/badge/terminus-v2.x-green.svg)](https://github.com/pantheon-systems/terminus)

Terminus Plugin that fetches metric data from the New Relic api:
1. It displays a list of sites without New Relic within an organization
2. It displays slowest performing sites using New Relic data (throughput, response time, Apdex)
3. It shows an alert if the environment is under stress using New Relic color coding
 [Pantheon](https://www.pantheon.io) sites.

Learn more about Terminus and Terminus Plugins at:
[https://pantheon.io/docs/terminus/plugins/](https://pantheon.io/docs/terminus/plugins/)



## Examples

1. Fetches metric data from `dev`
```
terminus newrelic-data:site my_site.dev
```
![Fetches metric data from dev](https://user-images.githubusercontent.com/1759794/200643729-44b3ca55-70da-428c-adda-231deac79037.png)

2. Displays all sites without New Relic under an organization by site plan.
```
terminus newrelic-data:org [ORG UUID]
```
![Displays all sites without New Relic under an organization by site plan](https://user-images.githubusercontent.com/1759794/200643828-4b991886-9c46-416d-a101-8204b36306df.png)

3. Displays all sites with or without New Relic under an organization by site plan.
```
terminus newrelic-data:org [ORG UUID] --all
```
![Displays all sites with or without New Relic under an organization by site plan](https://user-images.githubusercontent.com/1759794/200643934-f9100414-fd35-4678-b86a-b500d7bb0296.png)

4. Displays all sites with slowest response time under an organization. It provides an indicator if a site is in normal or in critical condition based on the New Relic health status.
```
terminus newrelic-data:org [ORG UUID] --overview
```
![Displays all sites with slowest response time under an organization](https://user-images.githubusercontent.com/1759794/200643992-bc77ea96-acf1-44d1-9fab-d2dd1aa0f21d.png)

## Installation
For help installing, see [Manage Plugins](https://pantheon.io/docs/terminus/plugins/)
```
Using composer:
mkdir -p ~/.terminus/plugins
composer create-project -d ~/.terminus/plugins pantheon-systems/terminus-newrelic-data-plugin:dev-master

Alternative installation:
curl https://github.com/pantheon-systems/terminus-newrelic-data-plugin/archive/2.0.3.tar.gz -L | tar -C ~/.terminus/plugins -xvz
composer update
```
## Things to remember
1. If invoking New Relic-data:org make sure you are an administrator of the organisation, otherwise it will only display sites where you are member. 

## Todo
1. To include screenshot of New relic metrics 

