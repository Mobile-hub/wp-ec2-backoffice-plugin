# Auto-Shutdown Script for EC2 Windows Instance
# Purpose: Monitors RDP sessions and stops instance after 2 hours of inactivity
# Managed by: WordPress EC2 Backoffice Plugin
# Schedule: Runs every 15 minutes via Windows Scheduled Task

<#
.SYNOPSIS
    Automatically stops an EC2 Windows instance after detecting 2 hours of RDP inactivity.

.DESCRIPTION
    This script checks for active RDP sessions using qwinsta. If active sessions are found,
    it updates the last activity timestamp in the Windows registry. If no active sessions
    are detected for 2 hours, the script stops the EC2 instance using AWS CLI.
    
    All actions are logged to the Windows Application Event Log under the source
    "WP-EC2-AutoShutdown".

.NOTES
    File Name      : auto-shutdown.ps1
    Prerequisite   : AWS CLI must be installed
    Prerequisite   : Instance must have IAM role with ec2:StopInstances permission
    Prerequisite   : Registry key HKLM:\SOFTWARE\WP-EC2-AutoShutdown must exist
    Prerequisite   : Event Log source "WP-EC2-AutoShutdown" must be registered
    
.EXAMPLE
    PowerShell.exe -ExecutionPolicy Bypass -NoProfile -File C:\Scripts\auto-shutdown.ps1
#>

# Error handling configuration
$ErrorActionPreference = "Stop"

# Configuration
$RegistryPath = "HKLM:\SOFTWARE\WP-EC2-AutoShutdown"
$LastActivityKey = "LastRDPActivity"
$InactivityThresholdHours = 2
$EventLogName = "Application"
$EventLogSource = "WP-EC2-AutoShutdown"

# Event IDs
$EVENT_ACTIVITY_DETECTED = 1000
$EVENT_INSTANCE_STOPPED = 1001
$EVENT_NO_ACTIVITY = 1002
$EVENT_ERROR = 1003

<#
.SYNOPSIS
    Writes a message to the Windows Event Log.

.PARAMETER EventId
    The Event ID for the log entry.

.PARAMETER EntryType
    The type of log entry (Information, Warning, Error).

.PARAMETER Message
    The message to log.
#>
function Write-AutoShutdownLog {
    param(
        [int]$EventId,
        [string]$EntryType,
        [string]$Message
    )
    
    try {
        Write-EventLog -LogName $EventLogName -Source $EventLogSource -EventId $EventId -EntryType $EntryType -Message $Message
    } catch {
        # Fallback to console if event log fails
        Write-Host "[$EntryType] $Message"
    }
}

<#
.SYNOPSIS
    Checks for active RDP sessions on the system.

.DESCRIPTION
    Uses qwinsta to query Windows session information and filters for active RDP sessions.

.OUTPUTS
    Returns $true if active RDP sessions are found, $false otherwise.
#>
function Test-ActiveRDPSessions {
    try {
        # Query Windows session information
        $Sessions = qwinsta
        
        # Filter for RDP sessions that are in Active state
        # RDP sessions typically show as "rdp-tcp" in the session name
        $ActiveRdpSessions = $Sessions | Select-String "rdp-tcp" | Where-Object { $_ -match "Active" }
        
        return ($null -ne $ActiveRdpSessions -and $ActiveRdpSessions.Count -gt 0)
    } catch {
        Write-AutoShutdownLog -EventId $EVENT_ERROR -EntryType Error -Message "Error checking RDP sessions: $($_.Exception.Message)"
        return $false
    }
}

<#
.SYNOPSIS
    Updates the last activity timestamp in the registry.

.PARAMETER Timestamp
    The timestamp to store (defaults to current time).
#>
function Update-LastActivityTimestamp {
    param(
        [DateTime]$Timestamp = (Get-Date)
    )
    
    try {
        # Ensure registry path exists
        if (-not (Test-Path $RegistryPath)) {
            New-Item -Path $RegistryPath -Force | Out-Null
        }
        
        # Store timestamp in ISO 8601 format for consistency
        Set-ItemProperty -Path $RegistryPath -Name $LastActivityKey -Value $Timestamp.ToString("o")
    } catch {
        Write-AutoShutdownLog -EventId $EVENT_ERROR -EntryType Error -Message "Error updating last activity timestamp: $($_.Exception.Message)"
    }
}

<#
.SYNOPSIS
    Retrieves the last activity timestamp from the registry.

.OUTPUTS
    Returns a DateTime object, or current time if no timestamp exists.
#>
function Get-LastActivityTimestamp {
    try {
        # Check if registry key exists
        if (-not (Test-Path $RegistryPath)) {
            # First run - initialize with current time
            $CurrentTime = Get-Date
            Update-LastActivityTimestamp -Timestamp $CurrentTime
            return $CurrentTime
        }
        
        # Get timestamp from registry
        $LastActivityStr = Get-ItemProperty -Path $RegistryPath -Name $LastActivityKey -ErrorAction SilentlyContinue | Select-Object -ExpandProperty $LastActivityKey
        
        if ($null -eq $LastActivityStr) {
            # Key doesn't exist - initialize with current time
            $CurrentTime = Get-Date
            Update-LastActivityTimestamp -Timestamp $CurrentTime
            return $CurrentTime
        }
        
        # Parse ISO 8601 timestamp
        return [DateTime]::Parse($LastActivityStr)
    } catch {
        Write-AutoShutdownLog -EventId $EVENT_ERROR -EntryType Error -Message "Error retrieving last activity timestamp: $($_.Exception.Message). Using current time."
        return Get-Date
    }
}

<#
.SYNOPSIS
    Stops the current EC2 instance using AWS CLI.

.DESCRIPTION
    Retrieves instance metadata and uses AWS CLI to stop the instance.
#>
function Stop-CurrentInstance {
    try {
        # Get instance ID from EC2 metadata service
        $InstanceId = Invoke-RestMethod -Uri "http://169.254.169.254/latest/meta-data/instance-id" -TimeoutSec 5
        
        # Get region from EC2 metadata service
        $Region = Invoke-RestMethod -Uri "http://169.254.169.254/latest/meta-data/placement/region" -TimeoutSec 5
        
        Write-AutoShutdownLog -EventId $EVENT_INSTANCE_STOPPED -EntryType Warning -Message "Stopping instance $InstanceId in region $Region due to $InactivityThresholdHours hours of RDP inactivity"
        
        # Stop the instance using AWS CLI
        $AwsCliPath = "C:\Program Files\Amazon\AWSCLIV2\aws.exe"
        
        if (-not (Test-Path $AwsCliPath)) {
            # Try alternative path
            $AwsCliPath = "aws"
        }
        
        & $AwsCliPath ec2 stop-instances --instance-ids $InstanceId --region $Region
        
        if ($LASTEXITCODE -ne 0) {
            throw "AWS CLI returned exit code $LASTEXITCODE"
        }
        
    } catch {
        Write-AutoShutdownLog -EventId $EVENT_ERROR -EntryType Error -Message "Error stopping instance: $($_.Exception.Message)"
        throw
    }
}

# Main execution logic
try {
    # Check for active RDP sessions
    $HasActiveRdpSessions = Test-ActiveRDPSessions
    
    if ($HasActiveRdpSessions) {
        # Active RDP session detected - update last activity timestamp
        $CurrentTime = Get-Date
        Update-LastActivityTimestamp -Timestamp $CurrentTime
        
        Write-AutoShutdownLog -EventId $EVENT_ACTIVITY_DETECTED -EntryType Information -Message "Active RDP session detected. Last activity updated to: $($CurrentTime.ToString('yyyy-MM-dd HH:mm:ss'))"
    } else {
        # No active RDP sessions - check inactivity duration
        $LastActivity = Get-LastActivityTimestamp
        $TimeSinceActivity = (Get-Date) - $LastActivity
        $HoursSinceActivity = [Math]::Round($TimeSinceActivity.TotalHours, 2)
        
        Write-AutoShutdownLog -EventId $EVENT_NO_ACTIVITY -EntryType Information -Message "No active RDP sessions. Time since last activity: $HoursSinceActivity hours (threshold: $InactivityThresholdHours hours)"
        
        # Check if inactivity threshold has been exceeded
        if ($TimeSinceActivity.TotalHours -ge $InactivityThresholdHours) {
            # Threshold exceeded - stop the instance
            Stop-CurrentInstance
        }
    }
    
} catch {
    # Log any unhandled errors
    Write-AutoShutdownLog -EventId $EVENT_ERROR -EntryType Error -Message "Unhandled error in auto-shutdown script: $($_.Exception.Message)`n$($_.ScriptStackTrace)"
    
    # Exit with error code
    exit 1
}

# Exit successfully
exit 0
