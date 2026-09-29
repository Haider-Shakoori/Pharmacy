#ifndef MyAppVersion
  #define MyAppVersion "0.1.0"
#endif

#define MyAppName "BusinessOS Pharmacy Offline"
#define MyAppPublisher "BusinessOS"
#define MyAppExeName "BusinessOS.Pharmacy.Manager.exe"

[Setup]
AppId={{E72A7480-A03E-4E20-A046-F71D577440E2}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
DefaultDirName={autopf}\BusinessOS\Pharmacy
DefaultGroupName=BusinessOS Pharmacy
OutputDir=..\dist\installer
OutputBaseFilename=BusinessOS-Pharmacy-Offline-Setup-{#MyAppVersion}
Compression=lzma2/max
SolidCompression=yes
WizardStyle=modern
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
PrivilegesRequired=admin
UninstallDisplayIcon={app}\manager\{#MyAppExeName}
DisableProgramGroupPage=yes
SetupLogging=yes

[Tasks]
Name: "desktopicon"; Description: "Create a desktop shortcut"; GroupDescription: "Shortcuts:"

[Files]
Source: "..\dist\application\*"; DestDir: "{app}\application"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "..\dist\runtime\*"; DestDir: "{app}\runtime"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "..\dist\manager\*"; DestDir: "{app}\manager"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "..\runtime\Caddyfile.template"; DestDir: "{app}"; Flags: ignoreversion
Source: "..\scripts\Install-Offline.ps1"; DestDir: "{app}\installer"; Flags: ignoreversion
Source: "..\scripts\Uninstall-Offline.ps1"; DestDir: "{app}\installer"; Flags: ignoreversion
Source: "..\dist\license-public-key.txt"; DestDir: "{app}"; Flags: ignoreversion

[Icons]
Name: "{group}\BusinessOS Pharmacy"; Filename: "{app}\manager\{#MyAppExeName}"
Name: "{group}\BusinessOS Pharmacy License"; Filename: "http://{code:GetComputerName}:8090/offline/license"
Name: "{autodesktop}\BusinessOS Pharmacy"; Filename: "{app}\manager\{#MyAppExeName}"; Tasks: desktopicon

[Run]
Filename: "{app}\manager\{#MyAppExeName}"; Description: "Open BusinessOS Pharmacy Server Manager"; Flags: postinstall nowait skipifsilent

[UninstallRun]
Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\installer\Uninstall-Offline.ps1"" -AppRoot ""{app}"" -DataRoot ""{commonappdata}\BusinessOS\Pharmacy"""; Flags: runhidden waituntilterminated

[Code]
var
  PharmacyPage: TInputQueryWizardPage;
  OwnerPage: TInputQueryWizardPage;
  InstallConfigPath: String;

procedure InitializeWizard;
begin
  PharmacyPage := CreateInputQueryPage(
    wpSelectDir,
    'Pharmacy details',
    'Tell BusinessOS which pharmacy is being installed.',
    'These values can be changed later in Pharmacy settings.'
  );
  PharmacyPage.Add('Pharmacy name:', False);

  OwnerPage := CreateInputQueryPage(
    PharmacyPage.ID,
    'Administrator account',
    'Create the first local BusinessOS Pharmacy owner.',
    'Use a strong password and keep it private.'
  );
  OwnerPage.Add('Owner name:', False);
  OwnerPage.Add('Owner email:', False);
  OwnerPage.Add('Owner password:', True);

  PharmacyPage.Values[0] := ExpandConstant('{param:PHARMACYNAME|My Pharmacy}');
  OwnerPage.Values[0] := ExpandConstant('{param:OWNERNAME|Administrator}');
  OwnerPage.Values[1] := ExpandConstant('{param:OWNEREMAIL|admin@businessos.local}');
  OwnerPage.Values[2] := ExpandConstant('{param:OWNERPASSWORD|}');
end;

function NextButtonClick(CurPageID: Integer): Boolean;
begin
  Result := True;

  if CurPageID = PharmacyPage.ID then
  begin
    if Trim(PharmacyPage.Values[0]) = '' then
    begin
      MsgBox('Enter the pharmacy name.', mbError, MB_OK);
      Result := False;
    end;
  end;

  if CurPageID = OwnerPage.ID then
  begin
    if (Trim(OwnerPage.Values[0]) = '') or
       (Trim(OwnerPage.Values[1]) = '') or
       (Length(OwnerPage.Values[2]) < 8) then
    begin
      MsgBox('Enter the owner name, email, and a password of at least 8 characters.', mbError, MB_OK);
      Result := False;
    end;
  end;
end;

function GetPharmacyName(Param: String): String;
begin
  Result := PharmacyPage.Values[0];
end;

function GetOwnerName(Param: String): String;
begin
  Result := OwnerPage.Values[0];
end;

function GetOwnerEmail(Param: String): String;
begin
  Result := OwnerPage.Values[1];
end;

function GetOwnerPassword(Param: String): String;
begin
  Result := OwnerPage.Values[2];
end;

function GetComputerName(Param: String): String;
begin
  Result := GetEnv('COMPUTERNAME');
end;


function PrepareToInstall(var NeedsRestart: Boolean): String;
var
  ConfigText: String;
begin
  Result := '';
  InstallConfigPath := ExpandConstant('{tmp}\BusinessOSPharmacy.install');

  ConfigText :=
    'PharmacyName=' + PharmacyPage.Values[0] + #13#10 +
    'OwnerName=' + OwnerPage.Values[0] + #13#10 +
    'OwnerEmail=' + OwnerPage.Values[1] + #13#10 +
    'OwnerPassword=' + OwnerPage.Values[2] + #13#10;

  if not SaveStringToFile(InstallConfigPath, ConfigText, False) then
    Result := 'BusinessOS could not prepare the secure installation configuration.';
end;

function GetInstallConfigPath(Param: String): String;
begin
  Result := InstallConfigPath;
end;


procedure CurStepChanged(CurStep: TSetupStep);
var
  ResultCode: Integer;
  PowerShellExe: String;
  Parameters: String;
begin
  if CurStep = ssPostInstall then
  begin
    PowerShellExe := ExpandConstant('{sys}\WindowsPowerShell\v1.0\powershell.exe');
    Parameters :=
      '-NoProfile -NonInteractive -ExecutionPolicy Bypass -File "' +
      ExpandConstant('{app}\installer\Install-Offline.ps1') +
      '" -AppRoot "' + ExpandConstant('{app}') +
      '" -DataRoot "' + ExpandConstant('{commonappdata}\BusinessOS\Pharmacy') +
      '" -ConfigPath "' + InstallConfigPath +
      '" -AppVersion "{#MyAppVersion}" -HttpPort 8090';

    WizardForm.StatusLabel.Caption := 'Configuring BusinessOS Pharmacy Offline...';

    if not Exec(PowerShellExe, Parameters, '', SW_HIDE, ewWaitUntilTerminated, ResultCode) then
      RaiseException('BusinessOS Pharmacy setup could not start the local configuration process.');

    if ResultCode <> 0 then
      RaiseException(Format(
        'BusinessOS Pharmacy local configuration failed (exit code %d). See C:\ProgramData\BusinessOS\Pharmacy\logs\install.log.',
        [ResultCode]
      ));
  end;
end;
