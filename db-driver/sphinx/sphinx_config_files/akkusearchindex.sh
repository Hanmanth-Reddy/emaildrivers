#!/bin/bash

if [ -n "$1" ]
then
	while [ -n "$1" ]
	  do
		/usr/bin/indexer $1_cand_masters --rotate --config /etc/sphinx/sphinx.conf
		/usr/bin/indexer $1_job_masters --rotate --config /etc/sphinx/sphinx.conf
		/usr/bin/indexer $1_cont_main --rotate --config /etc/sphinx/sphinx.conf
		/usr/bin/indexer $1_cont_delta --rotate --config /etc/sphinx/sphinx.conf
		/usr/bin/indexer $1_cand_main --rotate --config /etc/sphinx/sphinx.conf
		/usr/bin/indexer $1_cand_delta --rotate --config /etc/sphinx/sphinx.conf
		/usr/bin/indexer $1_comp_main --rotate --config /etc/sphinx/sphinx.conf
		/usr/bin/indexer $1_comp_delta --rotate --config /etc/sphinx/sphinx.conf
		/usr/bin/indexer $1_job_main --rotate --config /etc/sphinx/sphinx.conf
		/usr/bin/indexer $1_job_delta --rotate --config /etc/sphinx/sphinx.conf 
		shift
	done
else
    echo "Passing arguments missing (Ex: sh akkusearchindex.sh COMPANY_ID)"
fi