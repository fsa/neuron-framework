push:
	for server in $$(git remote -v | cut -f1 | uniq) ; do \
		echo "git push $$server:"; git push $$server ; \
	done
