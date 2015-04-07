== HitCounters ==

In [https://gerrit.wikimedia.org/r/150699/ MediaWiki 1.25], hit counters
have been removed.  The reason is given in the commit message:

: The hitcounter implementation in MediaWiki is flawed
: and needs removal. For proper metrics, it is suggested to use
: something like Piwik or Google Analytics.

More discussion can be found at
https://www.mediawiki.org/wiki/RFC/Removing_hit_counters_from_MediaWiki_core

If you wish to continue using the HitCounter's despite the flawed
implementation, this extension should help.

Note that some steps will be needed to maintain you current hit
count.  When those steps are understood, they'll be documented.

