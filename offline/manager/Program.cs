using System.Diagnostics;
using System.Net;
using System.Net.Sockets;
using System.Text;

namespace BusinessOS.Pharmacy.Manager;

internal static class Program
{
    [STAThread]
    private static void Main()
    {
        ApplicationConfiguration.Initialize();
        Application.Run(new MainForm());
    }
}

internal sealed class MainForm : Form
{
    private const int HttpPort = 8090;
    private readonly Label _webStatus = new();
    private readonly Label _phpStatus = new();
    private readonly Label _dbStatus = new();
    private readonly Label _queueStatus = new();
    private readonly Label _address = new();
    private readonly System.Windows.Forms.Timer _timer = new();

    public MainForm()
    {
        Text = "BusinessOS Pharmacy Server Manager";
        StartPosition = FormStartPosition.CenterScreen;
        MinimumSize = new Size(700, 520);
        Size = new Size(820, 600);
        Font = new Font("Segoe UI", 10F);
        BackColor = Color.FromArgb(248, 250, 252);

        var root = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            Padding = new Padding(24),
            ColumnCount = 1,
            RowCount = 7,
            AutoScroll = true,
        };

        root.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        root.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        root.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        root.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        root.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        root.RowStyles.Add(new RowStyle(SizeType.AutoSize));
        root.RowStyles.Add(new RowStyle(SizeType.Percent, 100F));

        var heading = new Label
        {
            AutoSize = true,
            Text = "BusinessOS Pharmacy Offline",
            Font = new Font("Segoe UI", 22F, FontStyle.Bold),
            ForeColor = Color.FromArgb(15, 23, 42),
            Margin = new Padding(0, 0, 0, 6),
        };
        root.Controls.Add(heading);

        var subheading = new Label
        {
            AutoSize = true,
            Text = "Local server, license and LAN status",
            ForeColor = Color.FromArgb(100, 116, 139),
            Margin = new Padding(0, 0, 0, 20),
        };
        root.Controls.Add(subheading);

        var services = new TableLayoutPanel
        {
            AutoSize = true,
            Dock = DockStyle.Top,
            ColumnCount = 2,
            Padding = new Padding(0),
            Margin = new Padding(0, 0, 0, 18),
        };
        services.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 55));
        services.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 45));

        AddStatusRow(services, "Web server", _webStatus);
        AddStatusRow(services, "PHP application server", _phpStatus);
        AddStatusRow(services, "Database", _dbStatus);
        AddStatusRow(services, "Queue worker", _queueStatus);
        root.Controls.Add(services);

        var addressPanel = new Panel
        {
            Height = 70,
            Dock = DockStyle.Top,
            BackColor = Color.White,
            Padding = new Padding(14),
            Margin = new Padding(0, 0, 0, 18),
        };
        var addressTitle = new Label
        {
            AutoSize = true,
            Text = "LAN address",
            Font = new Font("Segoe UI", 9F, FontStyle.Bold),
            ForeColor = Color.FromArgb(100, 116, 139),
            Location = new Point(14, 10),
        };
        _address.AutoSize = true;
        _address.Font = new Font("Consolas", 11F, FontStyle.Bold);
        _address.ForeColor = Color.FromArgb(15, 118, 110);
        _address.Location = new Point(14, 34);
        addressPanel.Controls.Add(addressTitle);
        addressPanel.Controls.Add(_address);
        root.Controls.Add(addressPanel);

        var primaryButtons = new FlowLayoutPanel
        {
            AutoSize = true,
            Dock = DockStyle.Top,
            FlowDirection = FlowDirection.LeftToRight,
            WrapContents = true,
            Margin = new Padding(0, 0, 0, 12),
        };
        primaryButtons.Controls.Add(Button("Open Pharmacy", () => OpenUrl(BaseUrl())));
        primaryButtons.Controls.Add(Button("License", () => OpenUrl(BaseUrl() + "/offline/license")));
        primaryButtons.Controls.Add(Button("Copy LAN Address", CopyAddress));
        root.Controls.Add(primaryButtons);

        var adminButtons = new FlowLayoutPanel
        {
            AutoSize = true,
            Dock = DockStyle.Top,
            FlowDirection = FlowDirection.LeftToRight,
            WrapContents = true,
            Margin = new Padding(0, 0, 0, 18),
        };
        adminButtons.Controls.Add(Button("Refresh Status", RefreshStatus, secondary: true));
        adminButtons.Controls.Add(Button("Restart Services", RestartServices, secondary: true));
        adminButtons.Controls.Add(Button("Open Diagnostics", OpenDiagnostics, secondary: true));
        root.Controls.Add(adminButtons);

        var note = new Label
        {
            AutoSize = true,
            MaximumSize = new Size(720, 0),
            Text = "BusinessOS Pharmacy continues to work without internet after activation. Internet is required again only when the signed offline license must be renewed.",
            ForeColor = Color.FromArgb(71, 85, 105),
        };
        root.Controls.Add(note);

        Controls.Add(root);

        Shown += (_, _) => RefreshStatus();
        _timer.Interval = 5000;
        _timer.Tick += (_, _) => RefreshStatus();
        _timer.Start();
    }

    private static void AddStatusRow(TableLayoutPanel table, string name, Label status)
    {
        var row = table.RowCount++;
        table.RowStyles.Add(new RowStyle(SizeType.AutoSize));

        var label = new Label
        {
            AutoSize = true,
            Text = name,
            Padding = new Padding(0, 8, 0, 8),
            ForeColor = Color.FromArgb(51, 65, 85),
        };

        status.AutoSize = true;
        status.Padding = new Padding(0, 8, 0, 8);
        status.Font = new Font("Segoe UI", 9F, FontStyle.Bold);

        table.Controls.Add(label, 0, row);
        table.Controls.Add(status, 1, row);
    }

    private static Button Button(string text, Action action, bool secondary = false)
    {
        var button = new Button
        {
            AutoSize = true,
            Text = text,
            Padding = new Padding(12, 6, 12, 6),
            Margin = new Padding(0, 0, 10, 8),
            FlatStyle = FlatStyle.Flat,
            BackColor = secondary ? Color.White : Color.FromArgb(13, 148, 136),
            ForeColor = secondary ? Color.FromArgb(30, 41, 59) : Color.White,
            Cursor = Cursors.Hand,
        };
        button.FlatAppearance.BorderColor = secondary
            ? Color.FromArgb(203, 213, 225)
            : Color.FromArgb(13, 148, 136);
        button.Click += (_, _) => action();

        return button;
    }

    private void RefreshStatus()
    {
        UpdateService(_webStatus, "BusinessOSPharmacyWeb");
        UpdateService(_phpStatus, "BusinessOSPharmacyPHP");
        UpdateService(_dbStatus, "BusinessOSPharmacyDB");
        UpdateService(_queueStatus, "BusinessOSPharmacyQueue");
        _address.Text = BaseUrl();
    }

    private static void UpdateService(Label label, string serviceName)
    {
        var output = Run("sc.exe", $"query {serviceName}");
        var running = output.Contains("RUNNING", StringComparison.OrdinalIgnoreCase);
        label.Text = running ? "Running" : "Stopped / unavailable";
        label.ForeColor = running ? Color.FromArgb(5, 150, 105) : Color.FromArgb(220, 38, 38);
    }

    private void RestartServices()
    {
        foreach (var service in new[]
        {
            "BusinessOSPharmacyDB",
            "BusinessOSPharmacyPHP",
            "BusinessOSPharmacyWeb",
            "BusinessOSPharmacyQueue",
        })
        {
            Run("sc.exe", $"stop {service}");
            Thread.Sleep(500);
            Run("sc.exe", $"start {service}");
        }

        RefreshStatus();
        MessageBox.Show("BusinessOS Pharmacy services were restarted.", "BusinessOS Pharmacy");
    }

    private static void OpenDiagnostics()
    {
        var sb = new StringBuilder();
        foreach (var service in new[]
        {
            "BusinessOSPharmacyDB",
            "BusinessOSPharmacyPHP",
            "BusinessOSPharmacyWeb",
            "BusinessOSPharmacyQueue",
        })
        {
            sb.AppendLine($"===== {service} =====");
            sb.AppendLine(Run("sc.exe", $"query {service}"));
        }

        var path = Path.Combine(Path.GetTempPath(), "BusinessOS-Pharmacy-Diagnostics.txt");
        File.WriteAllText(path, sb.ToString());
        Process.Start(new ProcessStartInfo(path) { UseShellExecute = true });
    }

    private void CopyAddress()
    {
        Clipboard.SetText(BaseUrl());
        MessageBox.Show("LAN address copied.", "BusinessOS Pharmacy");
    }

    private static string BaseUrl()
    {
        var address = Dns.GetHostAddresses(Dns.GetHostName())
            .FirstOrDefault(ip => ip.AddressFamily == AddressFamily.InterNetwork && !IPAddress.IsLoopback(ip));

        return $"http://{address ?? IPAddress.Loopback}:{HttpPort}";
    }

    private static void OpenUrl(string url)
    {
        Process.Start(new ProcessStartInfo(url) { UseShellExecute = true });
    }

    private static string Run(string file, string arguments)
    {
        try
        {
            using var process = Process.Start(new ProcessStartInfo
            {
                FileName = file,
                Arguments = arguments,
                UseShellExecute = false,
                RedirectStandardOutput = true,
                RedirectStandardError = true,
                CreateNoWindow = true,
            });

            if (process is null)
            {
                return string.Empty;
            }

            var output = process.StandardOutput.ReadToEnd();
            var error = process.StandardError.ReadToEnd();
            process.WaitForExit(5000);

            return output + error;
        }
        catch
        {
            return string.Empty;
        }
    }
}
