/// Invalidates stale asynchronous POS catalog results when a newer search
/// starts or the stock location changes.
class PosSearchGate {
  int _generation = 0;

  int begin() => ++_generation;

  void invalidate() => ++_generation;

  bool isCurrent(int generation) => generation == _generation;
}
