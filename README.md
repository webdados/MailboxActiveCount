# Mailbox Active Count for Freescout
Freescout module that shows the number of active conversations next to each mailbox name in the top bar "Mailbox" menu (both the dropdown, when you have access to multiple mailboxes, and the single "Mailbox" link, when you only have access to one).

A conversation counts as "active" when its status is Active (not Pending, Closed or Spam). The badge is hidden for mailboxes with zero active conversations. Counts are cached for 1 minute per mailbox to avoid extra queries on every page load.

PRs are welcome.

## Installation
* Download the latest version from this repository
* Upload/extract to the Modules folder on your Freescout install, inside a folder named MailboxActiveCount
* Go to Manage > Settings > Tools and Clear Cache
* Go to Modules and activate "Mailbox Active Count"

No further configuration is needed.

## To do
* Nothing planned yet — open an issue or PR if you'd like something added.
