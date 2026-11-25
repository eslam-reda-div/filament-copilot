# Security Policy

## Supported Versions

Security fixes are released for the latest minor version of Filament Copilot.

| Version | Supported |
| ------- | --------- |
| 1.x (latest minor) | ✅ |
| Older 1.x minors | ❌ Upgrade to the latest minor |
| < 1.0 | ❌ |

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues, discussions or pull requests.**

Report them privately through GitHub's private vulnerability reporting:

1. Go to the [Security tab](https://github.com/eslam-reda-div/filament-copilot/security) of this repository.
2. Click **Report a vulnerability** ([direct link](https://github.com/eslam-reda-div/filament-copilot/security/advisories/new)).
3. Describe the issue. Include:
   - the affected version(s)
   - steps to reproduce, or a proof of concept
   - the impact you expect (for example data exposure, privilege escalation, or XSS in the chat)

### What to expect

- An acknowledgement within **3 business days**.
- An initial assessment, and an estimated timeline for a fix, within **7 days**.
- Updates while the fix is in progress. The issue is disclosed once a patched release is available, in a GitHub Security Advisory that credits you unless you prefer to stay anonymous.

## Scope

In scope: the code in this repository. That includes the chat UI, the streaming endpoint, tool execution and approvals, conversation and feedback storage, and the management resources.

Out of scope:

- Vulnerabilities in dependencies such as Filament, Laravel, Livewire or Laravel AI. Report those to the respective project.
- Issues caused by an application's own tools, prompts or configuration. One example is setting `chat.html_input` to `allow`.
- What an AI model says in its replies, unless the package itself makes it exploitable.

## Hardening Notes for Users

- Keep `chat.html_input` set to `escape` (the default). Model output is untrusted.
- Mark tools that change data as approval-gated (Laravel AI's `Approvable`). Users then confirm each change before it runs.
- Restrict who can use the copilot with `authorizeUsing()`, and who can open the management dashboard with `managementGuard()`.
