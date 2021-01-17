# HitCounters

## Version history

v0.3.0

As found [here](https://github.com/wikimedia/mediawiki-extensions-HitCounters/releases/tag/0.3) (24 Nov 2015)

v0.3.0.6

- Fix: Several translation issues
- Fix - 23 Nov 2017: {{NUMBEROFVIEWS}} in MediaWiki 1.29 - [Bug: T142127](https://github.com/wikimedia/mediawiki-extensions-HitCounters/commit/213b2c6e40b5ef332381c82655d3ce227ace5c71)
- Build - 14 Aug 2018: Updating mediawiki/mediawiki-codesniffer to 18.0.0 - [diff](https://github.com/wikimedia/mediawiki-extensions-HitCounters/commit/822140f6d96974f5051449837e7f46a771d5f6a5#diff-df7ea4e51a49240fd52f0adb1b2ad9b2e2c8af3ee6a843defd40fd270e69595b)
- Add - 30 Jul 2018: Call AbuseFilter hooks for its page-views variable - [Bug: T159069](https://github.com/wikimedia/mediawiki-extensions-HitCounters/commit/33adf8a130cb72e3c9c246bb0139adbc62527df7)
- Fix - 22 Aug 2018: Type hint against IContextSource instead of RequestContext [diff](https://github.com/wikimedia/mediawiki-extensions-HitCounters/commit/c0afb68eb2704e55508f1d0771432e0400a50dbd)
- Fix - 25 Aug 2018: Use new syntax for AbuseFilter variables and deprecate the old ones - [Bug: T173889](https://github.com/wikimedia/mediawiki-extensions-HitCounters/commit/a3fc5c057960d3229591dd8139d3d76cfd284604)
- Fix -  1 Sep 2018: Escaping order with Language::convert. [diff](https://github.com/wikimedia/mediawiki-extensions-HitCounters/commit/3befcbb027f12017195bd1cea373d984bd171bd5)
- Fix - 31 May 2019: Fix cache key - [Bug: T163957](https://github.com/wikimedia/mediawiki-extensions-HitCounters/commit/04c68575651b6899bf4029934a0a9017305be6a5)
- Fix -  8 Jul 2019: Remove SiteStatsUpdate update that does nothing [diff](https://github.com/wikimedia/mediawiki-extensions-HitCounters/commit/c1634b1f32cce89b908c01e074673e72b356a033)

## Background

In [MediaWiki 1.25](https://gerrit.wikimedia.org/r/150699/), hit counters have been removed.  The reason is given in the commit message:

: The hitcounter implementation in MediaWiki is flawed and needs removal. For proper metrics, it is suggested to use something like Piwik or Google Analytics.

More discussion can be found at
https://www.mediawiki.org/wiki/RFC/Removing_hit_counters_from_MediaWiki_core

If you wish to continue using the HitCounter's despite the flawed implementation, this extension should help.

Note that some steps will be needed to maintain you current hit count.  When those steps are understood, they'll be documented.
