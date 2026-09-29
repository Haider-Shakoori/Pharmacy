#ifndef MyAppVersion
  #define MyAppVersion "0.1.0"
#endif

#define MyAppName "BusinessOS Pharmacy"
#define MyAppPublisher "BusinessOS.af"
#define MyAppExeName "businessos_pharmacy.exe"

[Setup]
AppId={{56D30DE8-7DC2-4D15-98AE-93A7B1DB1D2C}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
DefaultDirName={autopf}\BusinessOS Pharmacy
DefaultGroupName=BusinessOS Pharmacy
DisableProgramGroupPage=yes
PrivilegesRequired=admin
OutputDir=..\build\windows\installer
OutputBaseFilename=BusinessOS-Pharmacy-AllInOne-Setup
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
UninstallDisplayIcon={app}\{#MyAppExeName}
SetupLogging=yes
ChangesEnvironment=yes

[Files]
Source: "..\build\windows\x64\runner\Release\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "..\build\windows\local-server\*"; DestDir: "{app}\local-server"; Flags: ignoreversion recursesubdirs createallsubdirs

[Icons]
Name: "{autoprograms}\BusinessOS Pharmacy"; Filename: "{app}\{#MyAppExeName}"
Name: "{autoprograms}\BusinessOS Pharmacy Web"; Filename: "{app}\local-server\Open Pharmacy Web.cmd"
Name: "{autoprograms}\BusinessOS Pharmacy LAN Address"; Filename: "{app}\local-server\Show LAN Address.cmd"
Name: "{autodesktop}\BusinessOS Pharmacy"; Filename: "{app}\{#MyAppExeName}"; Tasks: desktopicon
Name: "{autodesktop}\BusinessOS Pharmacy Web"; Filename: "{app}\local-server\Open Pharmacy Web.cmd"; Tasks: webicon

[Tasks]
Name: "desktopicon"; Description: "Create a desktop shortcut for the Windows app"; GroupDescription: "Additional icons:"; Flags: unchecked
Name: "webicon"; Description: "Create a desktop shortcut for the full local web system"; GroupDescription: "Additional icons:"; Flags: unchecked

[Run]
Filename: "powershell.exe"; Parameters: "-NoProfile -NonInteractive -ExecutionPolicy Bypass -File ""{app}\local-server\scripts\install-service.ps1"""; StatusMsg: "Installing local Pharmacy server..."; Flags: runhidden waituntilterminated
Filename: "{app}\{#MyAppExeName}"; Description: "Launch BusinessOS Pharmacy"; Flags: nowait postinstall skipifsilent

[UninstallRun]
Filename: "powershell.exe"; Parameters: "-NoProfile -NonInteractive -ExecutionPolicy Bypass -File ""{app}\local-server\scripts\uninstall-service.ps1"""; Flags: runhidden waituntilterminated