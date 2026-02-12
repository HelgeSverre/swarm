# Security Policy

## Reporting a Vulnerability

If you discover a security vulnerability in Swarm, please report it by emailing **helge.sverre@gmail.com**.

**Please do not open a public issue for security vulnerabilities.**

We take all security reports seriously and will respond as quickly as possible.

## Security Considerations

### Terminal Tool

The `Terminal` tool allows execution of arbitrary shell commands and is **disabled by default** for security reasons.

- **Default State**: Disabled
- **To Enable**: Set `TERMINAL_ENABLED=true` in your `.env` file
- **Risk**: When enabled, the AI agent can execute commands on your system. Only enable this if you understand the risks and trust the AI's decision-making.
- **Best Practice**: Only enable in trusted, isolated development environments

### WebFetch Tool

The `WebFetch` tool includes SSRF (Server-Side Request Forgery) protections:

- Only `http` and `https` schemes are allowed
- Private and local IP addresses are blocked (RFC1918, loopback, link-local ranges)
- Localhost and `.local` domains are blocked
- Request timeouts are enforced

### Environment Variables

Never commit your `.env` file to version control. It contains sensitive API keys:

- `OPENAI_API_KEY`: Your OpenAI API key
- `TAVILY_API_KEY`: Tavily API key (if using web search features)

The `.env` file is already included in `.gitignore` to prevent accidental commits.

### Path Security

The `PathChecker` class validates all file operations:

- File access is restricted to the project directory by default
- Path traversal attempts are blocked
- Symlinks are validated to ensure they remain within allowed paths

## Supported Versions

We release security updates for the latest version only. Please ensure you're using the most recent version of Swarm.

## Security Best Practices

1. **Keep dependencies updated**: Run `composer update` regularly
2. **Review AI actions**: Monitor what the AI agent is doing, especially with tools enabled
3. **Use in isolated environments**: Run Swarm in development/testing environments, not production systems
4. **Limit API key permissions**: Use API keys with minimal necessary permissions
5. **Enable only needed tools**: Keep `TERMINAL_ENABLED=false` unless absolutely necessary
