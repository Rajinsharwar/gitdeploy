=== GitDeploy - Deploy from GitHub ===
Contributors: rajinsharwar
Tags: git, github, version control, workflow, dev tools
Requires at least: 5.0
Tested up to: 6.6
Requires PHP: 7.4
License: GPLv3
Stable tag: 1.0.0
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Easily deploy your website code from your GitHub repository directly to your WordPress environment. Manage your WP site codebase directly from GitHub.

== Description ==
GitDeploy is the plugin using which you can maintain your WordPress website's codebase directly from your GitHub repository. 
The main idea here is, to have your WordPress codebase in a GitHub repository as the only source of truth. You are expected to only modify the website's codebase by using the version-controlled GitHub development workflow.

I was inspired to create this plugin from WPVIP's architecture. Like they have in WPVIP, using this plugin, you can do such that WordPress plugins and themes directories can be added or removed via code commits to your GitHub repository. Every change you make in the GitHub repository is auto-deployed to your WordPress environment.
In any case if your WordPress website is out-of-sync, you can one-click pull the codebase from your GitHub repository, or even update your GitHub repository with your existing WordPress codebase.