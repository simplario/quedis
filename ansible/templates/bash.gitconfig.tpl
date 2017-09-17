[alias]

	# http://durdn.com/blog/2012/11/22/must-have-git-aliases-advanced-examples/
	# http://haacked.com/archive/2014/07/28/github-flow-aliases/
	# http://stackoverflow.com/questions/1057564/pretty-git-branch-graphs

	ls = log --pretty=format:"%C(yellow)%h%Cred%d\\ %Creset%s%Cblue\\ [%cn]" --decorate
	ld = log --pretty=format:"%C(yellow)%h\\ %ad%Cred%d\\ %Creset%s%Cblue\\ [%cn]" --decorate --date=relative
	lds = log --pretty=format:"%C(yellow)%h\\ %ad%Cred%d\\ %Creset%s%Cblue\\ [%cn]" --decorate --date=short

	lg1 = log --graph --abbrev-commit --decorate --date=relative --format=format:"%C(bold blue)%h%C(reset) - %C(bold green)(%ar)%C(reset) %C(white)%s%C(reset) %C(dim white)- %an%C(reset)%C(bold yellow)%d%C(reset)" --all
	lg2 = log --graph --abbrev-commit --decorate --format=format:"%C(bold blue)%h%C(reset) - %C(bold cyan)%aD%C(reset) %C(bold green)(%ar)%C(reset)%C(bold yellow)%d%C(reset)%n""          %C(white)%s%C(reset) %C(dim white)- %an%C(reset)" --all
	lg = !"git lg1"
    
	st = status
	co = checkout
	cob = checkout -b
	ec = config --global -e
	ecl = config --local -e
	up = !git pull --rebase --prune $@ && git submodule update --init --recursive
	cm = !git add -A && git commit -m
	save = !git add -A && git commit -m "SAVEPOINT"
	wip = !git add -u && git commit -m "WIP" 
	undo = reset HEAD~1 --mixed
	amend = commit -a --amend
	wipe = !git add -A && git commit -qm "WIPE SAVEPOINT" && git reset HEAD~1 --hard
	bclean = "!f() { git branch --merged ${1-master} | grep -v " ${1-master}$" | xargs -r git branch -d; }; f"
	bdone = "!f() { git checkout ${1-master} && git up && git bclean ${1-master}; }; f"
