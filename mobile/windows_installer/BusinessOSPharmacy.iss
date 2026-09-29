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
DefaultDirName={localappdata}\Programs\BusinessOS Pharmacy
DefaultGroupName=BusinessOS Pharmacy
DisableProgramGroupPage=yes
PrivilegesRequired=lowest
OutputDir=..\build\windows\installer
OutputBaseFilename=BusinessOS-Pharmacy-Setup
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
UninstallDisplayIcon={app}\{#MyAppExeName}
SetupLogging=yes

[Files]
Source: "..\build\windows\x64\runner\Release\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs

[Icons]
Name: "{autoprograms}\BusinessOS Pharmacy"; Filename: "{app}\{#MyAppExeName}"
Name: "{autodesktop}\BusinessOS Pharmacy"; Filename: "{app}\{#MyAppExeName}"; Tasks: desktopicon

[Tasks]
Name: "desktopicon"; Description: "Create a desktop shortcut"; GroupDescription: "Additional icons:"; Flags: unchecked

[Run]
Filename: "{app}\{#MyAppExeName}"; Description: "Launch BusinessOS Pharmacy"; Flags: nowait postinstall skipifsilent
