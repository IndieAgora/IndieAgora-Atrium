# Atrium

**AGPL-3.0 licensed WordPress plugin coordination system for sovereign platforms.**

Atrium is a coordinated WordPress plugin stack designed to unify social publishing, forum content, and video features into a single site experience.

Using this stack, you can connect a PeerTube instance with a phpBB-backed community data source and present everything through a social-media-style interface inside WordPress. The forum software itself can be used alongside the stack, or the system can work from an imported/full database backup, depending on your setup.

## What it does

Atrium provides three main user-facing areas:

### Connect
A social profile and wall-style experience for posting, sharing, and viewing user activity.

### Discuss
A forum-style interface inspired by modern discussion platforms. It surfaces forum content in a cleaner, more integrated format and displays a user’s forum activity within the wider platform.

### Stream
A built-in PeerTube interface inside your site. Users can browse videos, upload content, comment, rate, and interact with your connected PeerTube instance without needing to leave the main platform. A working PeerTube instance is required for this area.

## Installation

Install the required plugins and theme, then activate them in WordPress.

## Basic setup

Configure `ia-engine` with:

- the database prefixes you want to use
- the PeerTube instance you want to connect
- your administrator root password

Once configured, the system generates an authentication token for the PeerTube instance and keeps it refreshed automatically.

## In practice

After installation and configuration, the stack provides a unified platform where social posting, forum discussion, and PeerTube video activity can live together under one site.
