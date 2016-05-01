#!/usr/bin/env python
from __future__ import print_function
import mechanize
import json
import urllib
import mechanize
from bs4 import BeautifulSoup
import re
import urlparse
import lxml
import sys

first_arg = sys.argv[1]

def wiki_url(company):
    br = mechanize.Browser(factory=mechanize.RobustFactory())
    br.set_handle_robots(False)
    br.set_handle_equiv(False)
    br.addheaders = [('User-agent', 'Mozilla/5.0')] 
    br.open('http://www.google.com/') 
    # do the query
    br.select_form(name = 'f')
    br.form['q'] = company +' wikipedia' # query
    data = br.submit()
    soup = BeautifulSoup(data.read(), "lxml")
    links = []
    for a in soup.select('.r a'):
        links.append(urlparse.parse_qs(urlparse.urlparse(a['href']).query)['q'][0])
    wiki_links = [link for link in links if "wikipedia" in link]
    return wiki_links[0]

def extract_text(link):
    soup = BeautifulSoup(urllib.urlopen(link).read(), "lxml")
    coordinateCheck = (soup.p.get_text())
    if coordinateCheck[0:11] != "Coordinates":
    	print(coordinateCheck)
    else:
	print(soup.p.findNext('p').get_text())
extract_text(wiki_url(first_arg))
