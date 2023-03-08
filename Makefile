push: test
	git push

push-tags: test
	git push --tags

backup:
	for server in $$(git remote -v | cut -f1 | uniq) ; do \
		if [[ $$server != "origin" ]] ; then \
			echo "git push $$server"; git push $$server ; \
		fi \
	done

backup-tags:
	for server in $$(git remote -v | cut -f1 | uniq) ; do \
		if [[ $$server != "origin" ]] ; then \
			echo "git push $$server --tags"; git push $$server --tags ; \
		fi \
	done

test: composer-validate

composer-validate:
	composer validate --strict
