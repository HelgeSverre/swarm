# Swarm Security Considerations

## Overview

Swarm is a powerful AI-powered coding assistant that executes commands and modifies files on your system. This document outlines important security considerations and best practices for safe usage.

## ⚠️ Critical Security Warnings

### 1. **Unrestricted Command Execution**

The Terminal tool (`src/Tools/Terminal.php`) can execute **ANY** shell command without restrictions:

```php
public function execute(array $params): ToolResponse
{
    $command = $params['command'];
    // No validation or sandboxing!
    exec($command . ' 2>&1', $output, $returnCode);
}
```

**Risks:**
- System commands (rm -rf, format, etc.)
- Network operations (curl, wget)
- Process manipulation (kill, pkill)
- Privilege escalation attempts

**Mitigation:**
- Run Swarm with a restricted user account
- Use in isolated environments only
- Never run with elevated privileges (sudo/admin)

### 2. **File System Access**

The file tools have unrestricted access to the file system:

**Risks:**
- Reading sensitive files (.env, .ssh/*, passwords)
- Overwriting critical system files
- Creating files in system directories
- Path traversal attacks

**Mitigation:**
- Use filesystem permissions to restrict access
- Run in containers or VMs when possible
- Avoid running in directories with sensitive data

### 3. **API Key Exposure**

OpenAI API keys are stored in plaintext:

```bash
# In .env file
OPENAI_API_KEY=sk-...

# Or environment variable
export OPENAI_API_KEY=sk-...
```

**Risks:**
- Keys visible in process listings
- Keys in version control (if .env committed)
- Keys in logs or error messages

**Mitigation:**
- Never commit .env files
- Use secure key management systems in production
- Rotate keys regularly
- Monitor API usage

## Security Boundaries

### What Swarm CAN Do:
- Execute any shell command
- Read any accessible file
- Write to any writable location
- Make network requests via tools
- Access environment variables
- Spawn child processes

### What Swarm CANNOT Do:
- Bypass OS-level permissions
- Escape user privilege boundaries
- Access memory of other processes
- Directly modify system settings requiring elevation

## Deployment Recommendations

### 1. **Development Environment Only**

Swarm is designed for development environments. **DO NOT** deploy on production servers.

### 2. **Container Isolation**

Run Swarm in a Docker container for isolation:

```dockerfile
FROM php:8.3-cli
# Install dependencies
COPY . /app
WORKDIR /app
# Run as non-root user
USER nobody
CMD ["php", "cli.php"]
```

### 3. **Virtual Machine Usage**

For maximum isolation, run in a dedicated VM:
- Snapshot before use
- Revert after sessions
- Network isolation recommended

### 4. **User Permissions**

Create a restricted user for Swarm:

```bash
# Create restricted user
sudo useradd -m -s /bin/bash swarmuser

# Limit to specific directories
sudo mkdir /home/swarmuser/workspace
sudo chown swarmuser:swarmuser /home/swarmuser/workspace

# Run Swarm as restricted user
sudo -u swarmuser php cli.php
```

### 5. **Network Isolation**

Consider network restrictions:
- Firewall outbound connections
- Use private networks
- Monitor network activity

## Security Checklist

Before running Swarm:

- [ ] Using a non-production environment
- [ ] Running with restricted user account
- [ ] No sensitive data in working directory
- [ ] API keys properly secured
- [ ] Backups of important data
- [ ] Understanding of potential risks
- [ ] Isolation measures in place

## Tool-Specific Security Notes

### Terminal Tool
- Most dangerous tool - can execute any command
- No command validation or filtering
- Full access to shell features (pipes, redirects, etc.)

### WriteFile Tool
- Can overwrite any writable file
- Creates directories automatically
- No content filtering

### ReadFile Tool
- Can read any accessible file
- No content redaction
- Binary files converted to base64

### Search/Grep Tool
- Can search entire filesystem
- May expose sensitive data in search results

## Logging Security

Swarm logs may contain:
- User inputs (including commands)
- File contents
- API responses
- Error messages with paths

**Recommendations:**
- Secure log files appropriately
- Rotate logs regularly
- Avoid logging sensitive operations
- Review logs before sharing

## Future Security Enhancements

Potential improvements for safer operation:

1. **Command Allowlisting**: Restrict terminal commands to approved list
2. **Path Restrictions**: Limit file operations to specific directories
3. **Content Filtering**: Redact sensitive patterns from outputs
4. **Audit Logging**: Detailed audit trail of all operations
5. **Sandboxing**: Proper process isolation
6. **Role-Based Access**: Different permission levels for tools

## Incident Response

If you suspect malicious activity:

1. **Immediately terminate** the Swarm process
2. **Review logs** for unauthorized operations
3. **Check file modifications** in the working directory
4. **Rotate API keys** if compromised
5. **Restore from backups** if needed

## Responsible Use

Swarm is a powerful tool that requires responsible use:

- Always review generated commands before execution
- Understand what the AI is trying to do
- Maintain backups of important data
- Use in isolated environments
- Never share access with untrusted users
- Monitor system resources during use

## Summary

Swarm provides powerful automation capabilities but operates with the full privileges of the user running it. There are no built-in security restrictions on command execution or file access. Users must implement external security measures and use Swarm only in appropriate, isolated environments where the risks are understood and acceptable.

**Remember: With great power comes great responsibility. Use Swarm wisely.**